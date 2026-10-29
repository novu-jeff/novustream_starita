<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\NovupayStaritaBill;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Migrate missing online payments from sta_rita_db_test.bill to novupay_starita.starita_bills.
 * Only inserts rows that do not already exist (by reference_no). Safe to run multiple times.
 */
class MigrateOnlineBillsToStaritaBillsCommand extends Command
{
    protected $signature = 'migrate:online-bills-to-starita-bills
                            {--dry-run : Only report what would be migrated (default)}
                            {--run : Actually perform the migration}
                            {--limit=500 : Max number of bills to process}';

    protected $description = 'Migrate missing online transactions from sta_rita bill to novupay starita_bills (idempotent, use --run to execute)';

    public function handle(): int
    {
        $dryRun = ! $this->option('run');
        $limit = (int) $this->option('limit');

        if ($dryRun) {
            $this->info('DRY RUN – no changes will be written.');
        } else {
            $this->warn('LIVE RUN – writing to novupay_starita.starita_bills.');
        }

        $novupayConnection = (new NovupayStaritaBill())->getConnectionName();
        if (! Schema::connection($novupayConnection)->hasTable('starita_bills')) {
            $this->error('Table starita_bills not found on connection: ' . $novupayConnection);
            Log::error('migrate:online-bills-to-starita-bills: starita_bills table not found');
            return self::FAILURE;
        }

        if (! Schema::hasTable('bill') || ! Schema::hasTable('readings')) {
            $this->error('Required tables bill or readings not found on default connection.');
            return self::FAILURE;
        }

        // Bills that are online and paid, with reading loaded
        $bills = Bill::query()
            ->where('payment_method', 'online')
            ->where('isPaid', true)
            ->whereNotNull('reference_no')
            ->where('reference_no', '!=', '')
            ->with('reading')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        if ($bills->isEmpty()) {
            $this->info('No online paid bills found in sta_rita bill table.');
            return self::SUCCESS;
        }

        $existingRefs = NovupayStaritaBill::query()
            ->whereIn('reference_no', $bills->pluck('reference_no')->unique()->filter()->values()->all())
            ->pluck('reference_no')
            ->flip()
            ->all();

        $toMigrate = $bills->filter(fn ($b) => ! isset($existingRefs[$b->reference_no]));

        if ($toMigrate->isEmpty()) {
            $this->info('All ' . $bills->count() . ' online bill(s) already exist in starita_bills. Nothing to migrate.');
            return self::SUCCESS;
        }

        $this->info('Found ' . $toMigrate->count() . ' online bill(s) to migrate (already in starita_bills: ' . ($bills->count() - $toMigrate->count()) . ').');

        $staritaColumns = Schema::connection($novupayConnection)->getColumnListing('starita_bills');
        $inserted = 0;
        $errors = 0;

        foreach ($toMigrate as $bill) {
            try {
                $row = $this->billToStaritaRow($bill, $staritaColumns);
                if ($row === null) {
                    $this->warn("Skipped reference_no {$bill->reference_no}: missing reading or required data.");
                    continue;
                }

                if ($dryRun) {
                    $this->line('  [DRY RUN] Would insert: ' . $bill->reference_no . ' | account_no=' . ($row['account_no'] ?? '') . ' | paid_at=' . ($row['paid_at'] ?? ''));
                    $inserted++;
                    continue;
                }

                DB::connection($novupayConnection)->transaction(function () use ($row, $bill, &$inserted) {
                    // Idempotent: only insert if still missing (another process could have inserted)
                    $exists = NovupayStaritaBill::where('reference_no', $bill->reference_no)->exists();
                    if ($exists) {
                        return;
                    }
                    NovupayStaritaBill::create($row);
                    $inserted++;
                    Log::info('migrate:online-bills-to-starita-bills inserted', [
                        'reference_no' => $bill->reference_no,
                        'account_no' => $row['account_no'] ?? null,
                    ]);
                });
            } catch (\Throwable $e) {
                $errors++;
                $this->error("Failed for reference_no {$bill->reference_no}: " . $e->getMessage());
                Log::error('migrate:online-bills-to-starita-bills failed', [
                    'reference_no' => $bill->reference_no ?? null,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($dryRun) {
            $this->info("Dry run complete. Would insert {$inserted} row(s). Use --run to apply.");
        } else {
            $this->info("Migration complete. Inserted: {$inserted}, Errors: {$errors}.");
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Build a starita_bills row from sta_rita Bill (with reading). Returns null if missing required data.
     * @param  array<string>  $staritaColumns  Column names that exist on starita_bills (from getColumnListing).
     */
    private function billToStaritaRow(Bill $bill, array $staritaColumns): ?array
    {
        $reading = $bill->reading;
        if (! $reading) {
            return null;
        }

        $accountNo = (string) ($reading->account_no ?? '');
        $referenceNo = (string) ($bill->reference_no ?? '');
        if ($accountNo === '' || $referenceNo === '') {
            return null;
        }

        $paidAt = $this->parseDate($bill->date_paid ?? $bill->updated_at);
        $initiatedAt = $this->parseDate($bill->initiated_at ?? $bill->created_at);
        $syncedAt = $this->parseDate($bill->updated_at);

        $payload = [
            'customer' => ['name' => $bill->payor_name ?? ''],
            'payor' => $bill->payor_name ?? '',
            'reference_no' => $referenceNo,
            'migrated_from_sta_rita' => true,
            'previous_reading' => (int) ($reading->previous_reading ?? 0),
            'present_reading' => (int) ($reading->present_reading ?? 0),
            'is_high_consumption' => (bool) ($bill->isHighConsumption ?? false),
        ];

        $row = [
            'reference_no' => $referenceNo,
            'account_no' => $accountNo,
            'payor' => $bill->payor_name ?? null,
            'amount' => (float) ($bill->amount ?? 0),
            'status' => 'paid',
            'payload' => $payload,
            'initiated_at' => $initiatedAt,
            'paid_at' => $paidAt,
            'hitpay_reference' => $bill->hitpay_reference ?? null,
            'synced_to_sta_rita_at' => $syncedAt,
        ];

        // Only add columns that exist on starita_bills (remote table may not have previous_reading/present_reading/is_high_consumption)
        if (in_array('previous_reading', $staritaColumns, true) && in_array('present_reading', $staritaColumns, true)) {
            $row['previous_reading'] = (int) ($reading->previous_reading ?? 0);
            $row['present_reading'] = (int) ($reading->present_reading ?? 0);
        }
        if (in_array('is_high_consumption', $staritaColumns, true)) {
            $row['is_high_consumption'] = (bool) ($bill->isHighConsumption ?? false);
        }

        return $row;
    }

    private function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof Carbon) {
            return $value;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
