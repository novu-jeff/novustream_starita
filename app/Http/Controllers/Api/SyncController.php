<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MeterService;
use Carbon\Carbon;

class SyncController extends Controller
{
    public $meterService;

    public function __construct(MeterService $meterService) {
        $this->meterService = $meterService;
    }

    public function sync($reference_no)
    {
        $data = $this->meterService::getBill($reference_no);

        if ($data && isset($data['current_bill'])) {
            $data['current_bill'] = $this->computeBillPenalty($data['current_bill']);
        }

        return response()->json([
            'status' => $data ? 'success' : 'error',
            'data' => $data
        ]);
    }

    /**
     * Compute penalty and overdue details for a bill.
     */
    private function computeBillPenalty(array $bill): array
    {
        $amount = $bill['amount'] ?? 0;
        $dueDate = isset($bill['due_date']) ? Carbon::parse($bill['due_date']) : null;
        $today = Carbon::today();

        $penaltyRate = isset($bill['penalty_rate']) ? floatval($bill['penalty_rate']) : 0.15;

        $penaltyAmount = 0;
        $daysOverdue = 0;
        $penaltyDate = null;

        if ($dueDate && $today->gt($dueDate)) {
            $daysOverdue = $dueDate->diffInDays($today);
            $penaltyAmount = round($amount * $penaltyRate, 2);
            $penaltyDate = $dueDate->copy()->addDay();
        } elseif ($dueDate) {
            $penaltyDate = $dueDate->copy()->addDay();
        }

        $bill['computed_penalty'] = $penaltyAmount;
        $bill['computed_penalty_date'] = $penaltyDate ? $penaltyDate->format('Y-m-d') : null;
        $bill['computed_amount_after_due'] = $amount + $penaltyAmount;
        $bill['days_overdue'] = $daysOverdue;
        $bill['is_overdue'] = $daysOverdue > 0;

        return $bill;
    }
}
