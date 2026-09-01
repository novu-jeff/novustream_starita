<?php

namespace App\Console\Commands;

use App\Models\NovupayStaritaBill;
use App\Models\ReadingOffline;
use App\Services\OfflineMergeGuard;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove readings that re-billed an already-captured meter state after offline merge,
 * and re-queue the latest leftover offline row when it is a real next reading.
 */
class RepairStaleOfflineDuplicatesCommand extends Command
{
    protected $signature = 'readings:repair-stale-offline-duplicates
                            {--since=2026-09-01 : Only readings created on/after this date}
                            {--execute : Actually delete duplicates and re-queue missing readings}
                            {--include-paid : Also delete duplicates whose date_paid is on/after the reading created_at}';

    protected $description = 'Delete stale offline-merge duplicate readings (same prev/present as an older reading) and re-queue true next readings that were skip-all\'d';

    public function handle(OfflineMergeGuard $guard): int
    {
        $since = Carbon::parse($this->option('since'))->startOfDay();
        $execute = (bool) $this->option('execute');
        $includePaid = (bool) $this->option('include-paid');

        $this->info('Since: '.$since->toDateString());
        $this->info($execute ? 'Mode: EXECUTE' : 'Mode: DRY-RUN (no changes)');
        $this->newLine();

        $dups = DB::select("
            SELECT r.id, r.account_no, r.zone, r.reference_no, r.previous_reading, r.present_reading, r.created_at,
                   b.id as bill_id, b.reference_no as bill_ref, b.isPaid, b.amount, b.amount_paid, b.date_paid, b.payment_method,
                   older.id as older_id, older.reference_no as older_ref, older.created_at as older_created
            FROM readings r
            JOIN bill b ON b.reading_id = r.id
            JOIN readings older ON older.account_no = r.account_no
                AND older.id < r.id
                AND older.previous_reading = r.previous_reading
                AND older.present_reading = r.present_reading
            WHERE r.created_at >= ?
            ORDER BY r.account_no, r.id
        ", [$since->toDateTimeString()]);

        $seenReadingIds = [];
        $toDelete = [];
        $skippedPaid = [];
        foreach ($dups as $row) {
            if (isset($seenReadingIds[$row->id])) {
                continue;
            }
            $seenReadingIds[$row->id] = true;

            $paidAt = $row->date_paid ? Carbon::parse($row->date_paid) : null;
            $createdAt = Carbon::parse($row->created_at);
            $hasRealPayment = (int) $row->isPaid === 1 && $paidAt && $paidAt->gte($createdAt);

            if ($hasRealPayment && !$includePaid) {
                $skippedPaid[] = $row;
                continue;
            }

            // Consecutive 0-consumption months share prev+present; those are real bills, not stale re-uploads.
            $lastHadConsumption = (int) $row->present_reading !== (int) $row->previous_reading;
            if (!$lastHadConsumption) {
                continue;
            }

            $toDelete[] = $row;
        }

        $this->warn('Stale duplicate readings: '.count($toDelete));
        if ($toDelete) {
            $this->table(
                ['Reading', 'Account', 'Ref', 'Paid', 'Date paid', 'Older ref'],
                array_map(fn ($r) => [
                    $r->id,
                    $r->account_no,
                    $r->reference_no,
                    (int) $r->isPaid ? 'yes' : 'no',
                    $r->date_paid ?? '-',
                    $r->older_ref,
                ], $toDelete)
            );
        }
        if ($skippedPaid) {
            $this->warn('Left in place (paid on/after reading date; use --include-paid): '.count($skippedPaid));
            foreach ($skippedPaid as $r) {
                $this->line("  - {$r->account_no} {$r->reference_no} paid {$r->date_paid}");
            }
        }

        $requeue = $this->findRequeueRows($guard, $since);
        $this->newLine();
        $this->warn('Offline rows to re-queue as pending (true next reading): '.count($requeue));
        foreach ($requeue as $off) {
            $this->line("  - {$off->account_no} {$off->reference_no} {$off->previous_reading}->{$off->present_reading} (offline id {$off->id})");
        }

        if (!$execute) {
            $this->newLine();
            $this->info('Dry-run only. Re-run with --execute to apply.');
            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $deleted = 0;
            foreach ($toDelete as $row) {
                $this->deleteDuplicateReading($row);
                $deleted++;
            }

            foreach ($requeue as $off) {
                $off->update([
                    'status' => 'pending',
                    'synced_at' => null,
                    'merged_into_reading_id' => null,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Repair failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info("Deleted {$deleted} duplicate reading(s). Re-queued ".count($requeue).' offline row(s).');
        return self::SUCCESS;
    }

    /**
     * @return list<ReadingOffline>
     */
    private function findRequeueRows(OfflineMergeGuard $guard, Carbon $since): array
    {
        $skipped = ReadingOffline::query()
            ->where('status', 'skipped_duplicate')
            ->whereNull('merged_into_reading_id')
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->get()
            ->groupBy('account_no');

        $requeue = [];
        foreach ($skipped as $accountNo => $rows) {
            $winner = $guard->pickWinner($rows);
            $account = \App\Models\UserAccounts::where('account_no', $accountNo)->first();
            $mergeDate = $guard->resolveMergeBillingDate($winner, $account);
            if ($guard->findConflictingReading($winner, $mergeDate)) {
                continue;
            }
            $requeue[] = $winner;
        }

        return $requeue;
    }

    private function deleteDuplicateReading(object $row): void
    {
        $readingId = (int) $row->id;
        $billId = (int) $row->bill_id;
        $referenceNo = $row->reference_no;

        ReadingOffline::where('merged_into_reading_id', $readingId)
            ->update([
                'merged_into_reading_id' => null,
                'status' => 'skipped_duplicate',
                'synced_at' => now(),
            ]);

        DB::table('bill_breakdown')->where('bill_id', $billId)->delete();
        DB::table('bill_discount')->where('bill_id', $billId)->delete();
        if (Schema::hasTable('bill_adjustments')) {
            DB::table('bill_adjustments')->where('bill_id', $billId)->delete();
        }
        if (Schema::hasTable('reading_adjustments')) {
            DB::table('reading_adjustments')->where('reading_id', $readingId)->delete();
        }
        if (Schema::hasTable('partial_payments')) {
            DB::table('partial_payments')->where('reading_id', $readingId)->delete();
        }
        if (Schema::hasTable('advance_payments')) {
            DB::table('advance_payments')->where('reading_id', $readingId)->delete();
        }

        DB::table('bill')->where('id', $billId)->delete();
        DB::table('readings')->where('id', $readingId)->delete();

        $this->voidNovupayBill($referenceNo);
    }

    private function voidNovupayBill(string $referenceNo): void
    {
        try {
            $connection = (new NovupayStaritaBill())->getConnectionName();
            if (!Schema::connection($connection)->hasTable('starita_bills')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $nb = NovupayStaritaBill::where('reference_no', $referenceNo)->first();
        if (!$nb) {
            return;
        }
        if (strtolower((string) $nb->status) === 'paid') {
            // Leftover QR from a prior cycle — do not let novupay:sync-readings recreate the duplicate bill.
            if (!$nb->synced_to_sta_rita_at) {
                $nb->synced_to_sta_rita_at = now();
                $nb->save();
            }
            $this->warn("  Novupay starita_bill {$referenceNo} is paid — marked synced, left paid.");
            return;
        }
        $nb->status = 'voided';
        $nb->synced_to_sta_rita_at = $nb->synced_to_sta_rita_at ?? now();
        $nb->save();
    }
}
