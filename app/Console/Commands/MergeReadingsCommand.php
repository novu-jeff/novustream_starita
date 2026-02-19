<?php

namespace App\Console\Commands;

use App\Http\Controllers\OfflineSyncController;
use App\Models\Reading;
use App\Models\ReadingOffline;
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
                            {--dry-run : Only show how many would be merged and run validation checks, do not merge}';

    protected $description = 'Merge pending readings_offline into readings and create/update bills';

    public function handle(OfflineSyncController $controller): int
    {
        $limitRaw = $this->option('limit');
        $limit = ($limitRaw !== null && $limitRaw !== '') ? (int) $limitRaw : null;
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            return $this->dryRun($limit);
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
    private function dryRun(?int $limit): int
    {
        $query = ReadingOffline::where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'pending');
            })
            ->whereNull('synced_at')
            ->whereNull('merged_into_reading_id')
            ->orderBy('id');
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

        return $hasIssues ? 1 : 0;
    }
}
