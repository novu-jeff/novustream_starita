<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Reading;
use App\Models\ReadingOffline;
use App\Models\NovupayStaritaBill;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sync from Novupay starita_bills (QR payments) into sta-rita readings_offline.
 * Use reference_no as idempotent key. Run via cron or manually.
 * Merged records can then be processed by POST /api/readings/merge.
 */
class SyncNovupayReadingsCommand extends Command
{
    protected $signature = 'novupay:sync-readings {--limit=100}';
    protected $description = 'Sync starita_bills from Novupay into readings_offline (reference_no = key)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        try {
            if (!\Schema::hasTable('starita_bills')) {
                $this->warn('Table starita_bills not found. Ensure Novupay has run migrations against this DB.');
                Log::warning('SyncNovupayReadings: starita_bills table not found');
                return self::FAILURE;
            }

            $bills = NovupayStaritaBill::whereIn('status', ['initiated', 'paid', 'pending'])
                ->orderBy('id')
                ->limit($limit)
                ->get();

            $count = 0;
            foreach ($bills as $nb) {
                try {
                    ReadingOffline::updateOrCreate(
                        ['reference_no' => $nb->reference_no],
                        [
                            'account_no'        => $nb->account_no ?? '',
                            'previous_reading'  => $nb->previous_reading,
                            'present_reading'   => $nb->present_reading,
                            'consumption'       => $nb->present_reading && $nb->previous_reading
                                ? (int) $nb->present_reading - (int) $nb->previous_reading
                                : null,
                            'reader_name'       => 'Novupay',
                            'zone'              => null,
                            'source'            => 'novupay',
                            'payload'           => $nb->payload ?? [],
                        ]
                    );
                    $count++;
                } catch (\Throwable $e) {
                    Log::error('SyncNovupayReadings: failed for reference_no', [
                        'reference_no' => $nb->reference_no ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->info("Synced {$count} Novupay Starita bills into readings_offline.");
            Log::info('SyncNovupayReadings completed', ['count' => $count]);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('SyncNovupayReadings failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
