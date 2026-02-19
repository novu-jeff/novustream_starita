<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

/**
 * Backfill payor_name for bills where it is null, using the account holder's name.
 */
class BackfillPayorNameCommand extends Command
{
    protected $signature = 'bills:backfill-payor-name
                            {--limit=500 : Max bills to update per run}
                            {--dry-run : Only show count, do not update}';
    protected $description = 'Set payor_name from account holder for bills where it is null';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $bills = Bill::with('reading.concessionaire.user')
            ->whereNull('payor_name')
            ->whereHas('reading')
            ->limit($limit)
            ->get();

        $updated = 0;
        foreach ($bills as $bill) {
            $payor = optional(optional($bill->reading->concessionaire)->user)->name ?? 'Sta. Rita Customer';
            if (!$dryRun) {
                $bill->update(['payor_name' => $payor]);
            }
            $updated++;
        }

        if ($dryRun) {
            $this->info("Dry run: {$updated} bill(s) would be updated with payor_name.");
        } else {
            $this->info("Updated {$updated} bill(s) with payor_name.");
        }

        return self::SUCCESS;
    }
}
