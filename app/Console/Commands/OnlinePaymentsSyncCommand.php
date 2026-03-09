<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * Run novupay:sync-readings then readings:merge.
 * Used by the scheduler so online payments sync automatically every few minutes.
 * Manual sync button uses the same logic via NovupaySyncController.
 */
class OnlinePaymentsSyncCommand extends Command
{
    protected $signature = 'online-payments:sync
                            {--limit=500 : Max starita_bills to sync and max readings_offline to merge}';

    protected $description = 'Sync Novupay starita_bills to readings_offline, then merge into readings and bills (runs automatically on schedule)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit') ?: 500;

        try {
            if (!Schema::connection('novupay_starita')->hasTable('starita_bills')) {
                $this->warn('starita_bills table not found; skipping.');
                return 0;
            }
        } catch (\Throwable $e) {
            $this->warn('Novupay connection not available: ' . $e->getMessage());
            return 0;
        }

        try {
            $this->line('[' . now()->format('Y-m-d H:i:s') . '] Online payments sync started (limit=' . $limit . ')');
            $this->info('Running novupay:sync-readings...');
            Artisan::call('novupay:sync-readings', ['--limit' => $limit]);
            $this->line(trim(Artisan::output()));

            $this->info('Running readings:merge...');
            Artisan::call('readings:merge', ['--limit' => $limit]);
            $this->line(trim(Artisan::output()));

            $this->info('Online payments sync finished.');
            $this->line('[' . now()->format('Y-m-d H:i:s') . '] Done.');
            return 0;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            $this->line('[' . now()->format('Y-m-d H:i:s') . '] Failed.');
            return 1;
        }
    }
}
