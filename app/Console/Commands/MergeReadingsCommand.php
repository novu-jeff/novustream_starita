<?php

namespace App\Console\Commands;

use App\Http\Controllers\OfflineSyncController;
use App\Models\Reading;
use App\Models\ReadingOffline;
use App\Services\MergeBillReadingDatesService;
use App\Services\MeterService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Merge pending readings_offline into readings and create bills (same as POST /api/readings/merge).
 * Use from cron or terminal; no Bearer token required.
 * Validates: no duplicate account_no in pending batch; dry-run also checks for already-merged in readings.
 */
class MergeReadingsCommand extends Command
{
    protected $signature = 'readings:merge
                            {--limit= : Max number to merge per run (omit for no limit)}
                            {--dry-run : Only show how many would be merged and run validation checks, do not merge}
                            {--show-dates : With --dry-run, show create_breakdown date and bill/reading dates that would be stored}';

    protected $description = 'Merge pending readings_offline into readings and create/update bills';

    public function handle(
        OfflineSyncController $controller,
        MergeBillReadingDatesService $mergeBillReadingDatesService,
        MeterService $meterService
    ): int {
        $limitRaw = $this->option('limit');
        $limit = ($limitRaw !== null && $limitRaw !== '') ? (int) $limitRaw : null;
        $dryRun = $this->option('dry-run');
        $showDates = $this->option('show-dates');

        if ($showDates && !$dryRun) {
            $this->error('Use --show-dates together with --dry-run (does not merge).');

            return 1;
        }

        if ($dryRun) {
            return $this->dryRun($limit, $showDates, $mergeBillReadingDatesService, $meterService);
        }

        $payload = $limit !== null ? ['limit' => $limit] : [];
        $request = Request::create('/api/readings/merge', 'POST', $payload);
        $request->setUserResolver(function () {
            return \App\Models\Admin::first();
        });

        $response = $controller->merge($request);
        $data = $response->getData(true);

        $count = $data['count'] ?? 0;
        $errors = $data['errors'] ?? [];

        $this->info("Merged: {$count} reading(s).");

        if (!empty($errors)) {
            $this->warn('Errors: ' . count($errors));
            foreach (array_slice($errors, 0, 10) as $e) {
                $ref = $e['reference_no'] ?? '?';
                $acc = isset($e['account_no']) ? ' (account_no: ' . $e['account_no'] . ')' : '';
                $this->line('  - ' . $ref . $acc . ': ' . ($e['error'] ?? ''));
            }
            if (count($errors) > 10) {
                $this->line('  ... and ' . (count($errors) - 10) . ' more.');
            }
        }

        return 0;
    }

    /**
     * Dry-run: report pending count and run validation (duplicate account_no in batch, already in readings).
     */
    private function dryRun(
        ?int $limit,
        bool $showDates,
        MergeBillReadingDatesService $datesService,
        MeterService $meterService
    ): int {
        $query = ReadingOffline::eligibleForMerge()->orderBy('id');
        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }
        $pending = $query->get();

        $limitLabel = $limit !== null ? "limit={$limit}" : 'no limit';
        $this->info("Dry run: {$pending->count()} pending offline reading(s) would be merged ({$limitLabel}).");

        $hasIssues = false;

        // 1) Duplicate account_no in pending batch (multiple readings_offline for same account)
        $byAccount = $pending->groupBy('account_no');
        $duplicateAccounts = $byAccount->filter(fn ($rows) => $rows->count() > 1);
        if ($duplicateAccounts->isNotEmpty()) {
            $hasIssues = true;
            $this->warn('Duplicate account_no in pending readings_offline (multiple pending readings per account):');
            foreach ($duplicateAccounts as $accountNo => $rows) {
                $refs = $rows->pluck('reference_no')->implode(', ');
                $this->line("  - Account {$accountNo} has {$rows->count()} pending: [{$refs}]");
            }
        }

        // 2) Already merged: account_no + same month/year already exists in readings
        $alreadyInReadings = [];
        foreach ($pending as $off) {
            $year = $off->created_at?->year ?? now()->year;
            $month = $off->created_at?->month ?? now()->month;
            $exists = Reading::where('account_no', $off->account_no)
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->exists();
            if ($exists) {
                $alreadyInReadings[] = [
                    'reference_no' => $off->reference_no,
                    'account_no'   => $off->account_no,
                    'period'       => "{$year}-" . str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                ];
            }
        }
        if (!empty($alreadyInReadings)) {
            $hasIssues = true;
            $this->warn('Already in readings (account + period already merged):');
            foreach (array_slice($alreadyInReadings, 0, 20) as $e) {
                $this->line("  - {$e['reference_no']} (account {$e['account_no']}, period {$e['period']})");
            }
            if (count($alreadyInReadings) > 20) {
                $this->line('  ... and ' . (count($alreadyInReadings) - 20) . ' more.');
            }
        }

        if (!$hasIssues) {
            $this->info('No duplicate account_no or already-merged conflicts detected.');
        }

        if ($showDates && $pending->isNotEmpty()) {
            $this->newLine();
            $this->info('Date preview — values used during merge (no writes):');
            $this->line('  • create_breakdown `date` = merge billing date (from offline row or zone ReadingDate bill_period_to).');
            $this->line('  • After merge, bill + reading timestamps match ReadingController when zone has active ReadingDate.');
            $this->newLine();

            $previewRows = [];
            $cap = 40;
            foreach ($pending->take($cap) as $off) {
                $account = $meterService->getAccount($off->account_no);
                if (!$account) {
                    $previewRows[] = [
                        $off->reference_no,
                        substr((string) $off->account_no, 0, 14),
                        '—',
                        'account not found',
                        '—',
                        '—',
                    ];

                    continue;
                }

                $breakdownDate = $datesService->previewCreateBreakdownDate($off, $account);
                $stored = $datesService->previewStoredDatesForAccount($account);
                if (empty($stored['resolved'])) {
                    $reason = $stored['reason'] ?? 'unknown';
                    $previewRows[] = [
                        $off->reference_no,
                        substr((string) $off->account_no, 0, 14),
                        $breakdownDate,
                        'not applied',
                        $reason,
                        '—',
                    ];

                    continue;
                }

                $previewRows[] = [
                    $off->reference_no,
                    substr((string) $off->account_no, 0, 14),
                    $breakdownDate,
                    $stored['due_date'] ?? '—',
                    $stored['penalty_date'] ?? '—',
                    $stored['disconnection_date'] ?? '—',
                ];
            }

            $this->table(
                ['reference_no', 'account', 'create_breakdown date', 'due (stored)', 'penalty (stored)', 'disconnect (stored)'],
                $previewRows
            );

            if ($pending->count() > $cap) {
                $this->warn("Showing first {$cap} row(s) only. Use --limit to narrow or increase cap in MergeReadingsCommand.");
            }
        }

        return $hasIssues ? 1 : 0;
    }
}
