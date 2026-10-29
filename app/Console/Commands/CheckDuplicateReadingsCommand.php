<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Reading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Check and optionally remove duplicate readings/bills per account per month.
 * Duplicates = same account_no + same month; only the FIRST (oldest by id) is kept.
 *
 * Usage:
 *   php artisan duplicate-readings:check --month=2 --year=2026          # dry-run (default)
 *   php artisan duplicate-readings:check --month=2 --year=2026 --execute # actually delete
 */
class CheckDuplicateReadingsCommand extends Command
{
    protected $signature = 'duplicate-readings:check
                            {--month= : Month (1-12), e.g. 2 for February}
                            {--year= : Year (default: current year)}
                            {--execute : Actually delete duplicates (default is dry-run only)}';

    protected $description = 'Find duplicate readings/bills per account per month (dry-run by default); use --execute to delete';

    public function handle(): int
    {
        $month = $this->option('month');
        $year = (int) ($this->option('year') ?: now()->year);

        if ($month === null || $month === '') {
            $this->error('Please specify --month=2 for February (or 1-12).');
            return self::FAILURE;
        }
        $month = (int) $month;
        if ($month < 1 || $month > 12) {
            $this->error('Month must be 1-12.');
            return self::FAILURE;
        }

        $execute = $this->option('execute');

        $this->info('Finding duplicate readings for ' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT));
        $this->info($execute ? 'Mode: EXECUTE (will delete)' : 'Mode: DRY-RUN (no changes)');
        $this->newLine();

        // Readings in the given month, grouped by account_no
        $duplicates = DB::table('readings')
            ->select('id', 'account_no', 'previous_reading', 'present_reading', 'created_at')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('account_no')
            ->orderBy('id')
            ->get();

        $byAccount = $duplicates->groupBy('account_no');
        $toDelete = [];
        foreach ($byAccount as $accountNo => $rows) {
            if ($rows->count() > 1) {
                $first = $rows->first();
                foreach ($rows as $r) {
                    if ((int) $r->id !== (int) $first->id) {
                        $bill = Bill::where('reading_id', $r->id)->first();
                        if ($bill && $bill->isPaid) {
                            continue; // Do not delete paid bills
                        }
                        $toDelete[] = $r;
                    }
                }
            }
        }

        if (empty($toDelete)) {
            $this->info('No duplicate readings found for this period.');
            return self::SUCCESS;
        }

        $this->warn('Found ' . count($toDelete) . ' duplicate reading(s) to remove (keeping first per account):');
        $this->newLine();

        $tableRows = [];
        foreach ($toDelete as $r) {
            $bill = Bill::where('reading_id', $r->id)->first();
            $tableRows[] = [
                $r->id,
                $r->account_no,
                $r->previous_reading ?? '-',
                $r->present_reading ?? '-',
                $r->created_at ?? '-',
                $bill ? $bill->reference_no : '-',
                $bill ? ($bill->isPaid ? 'Yes' : 'No') : '-',
            ];
        }
        $this->table(
            ['Reading ID', 'Account', 'Prev', 'Present', 'Created', 'Bill Ref', 'Paid'],
            $tableRows
        );

        $readingIds = array_map(fn ($r) => $r->id, $toDelete);
        $billCount = Bill::whereIn('reading_id', $readingIds)->count();

        $this->newLine();
        $this->warn('Summary:');
        $this->line('  - Readings to delete: ' . count($toDelete));
        $this->line('  - Bills to delete (cascade): ' . $billCount);
        $this->line('  - bill_breakdown, bill_discount will cascade from bill');

        if (!$execute) {
            $this->newLine();
            $this->info('This was a dry-run. No changes made.');
            $this->info('Run with --execute to actually delete these records.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Proceed with deletion?', false)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($toDelete as $r) {
            $reading = Reading::find($r->id);
            if ($reading) {
                $reading->delete(); // cascades to bill -> bill_breakdown, bill_discount
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} duplicate reading(s) and their bills.");
        return self::SUCCESS;
    }
}
