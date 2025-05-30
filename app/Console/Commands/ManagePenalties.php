<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bill;
use App\Models\BillBreakdown;
use Carbon\Carbon;
use App\Services\PaymentBreakdownService;
class ManagePenalties extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:manage-penalties';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        Bill::with('reading')
            ->where('isPaid', false)
            ->chunk(100, function ($bills) {
                $paymentBreakdownService = new PaymentBreakdownService;
                $penalties = $paymentBreakdownService::getPenalty();

                $currentTimestamp = Carbon::parse('2025-06-12');

                foreach ($bills as $bill) {

                    if ($bill->hasPenalty) {
                        continue;
                    }

                    $dueTimestamp = Carbon::parse($bill->due_date)->startOfDay();

                    if ($currentTimestamp->lte($dueTimestamp)) {
                        continue;
                    }

                    $dueCount = $currentTimestamp->diffInDays($dueTimestamp);

                    $penalty = $this->findPenaltyForDueCount($penalties, $dueCount);

                    if ($penalty === null) {
                        continue; 
                    }

                    $this->applyPenaltyToBill($bill, $penalty);
                }
            });
    }

    /**
     * Find the penalty range that fits the due count.
     */
    protected function findPenaltyForDueCount($penalties, int $dueCount)
    {
        foreach ($penalties as $penalty) {
            $from = (int) $penalty['due_from'];
            $to = $penalty['due_to'] === '*' ? PHP_INT_MAX : (int) $penalty['due_to'];

            if ($dueCount >= $from && $dueCount <= $to) {
                return $penalty;
            }
        }
        return null;
    }

    /**
     * Apply penalty to a single bill.
     */
    protected function applyPenaltyToBill(Bill $bill, $penalty)
    {
        $amountPayable = $bill->amount;
        $penaltyAmount = 0;

        if (strtolower($penalty->amount_type) === 'percentage') {
            $penaltyAmount = $amountPayable * ($penalty->amount);
        } else {
            $penaltyAmount = $penalty->amount;
        }

        $totalAmount = $amountPayable + $penaltyAmount;

        $bill->update([
            'amount_after_due' => $totalAmount,
            'penalty' => $penaltyAmount,
            'hasPenalty' => true,
        ]);
    }

}
