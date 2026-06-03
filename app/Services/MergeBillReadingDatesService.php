<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Reading;
use App\Models\ReadingDate;
use App\Models\ReadingOffline;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class MergeBillReadingDatesService
{
    /**
     * After offline merge, align bill + reading timestamps with ReadingController::store
     * when an active zone ReadingDate exists.
     */
    public function applyZoneReadingDatesToMergedBill(object $account, string $referenceNo): void
    {
        $bill = Bill::where('reference_no', $referenceNo)->first();
        $reading = Reading::where('reference_no', $referenceNo)->first();
        if (!$bill || !$reading) {
            return;
        }

        $zone = Zone::where('zone', $account->zone ?? '')->first();
        if (!$zone) {
            return;
        }

        $readingDate = ReadingDate::where('zone_id', $zone->id)
            ->where('is_active', 1)
            ->first();

        if (!$readingDate) {
            return;
        }

        $billPeriodFrom = Carbon::parse($readingDate->bill_period_from);
        $billPeriodTo = Carbon::parse($readingDate->bill_period_to);
        $dueDate = Carbon::parse($readingDate->due_date);
        $penaltyDate = $dueDate->copy()->addDay();
        $disconnectionDate = $dueDate->copy()->addDays(7);

        $bill->bill_period_from = $billPeriodFrom->format('Y-m-d H:i:s');
        $bill->bill_period_to = $billPeriodTo->format('Y-m-d H:i:s');
        $bill->due_date = $dueDate->format('Y-m-d H:i:s');
        if (Schema::hasColumn('bill', 'penalty_date')) {
            $bill->penalty_date = $penaltyDate->format('Y-m-d H:i:s');
        }
        if (Schema::hasColumn('bill', 'disconnection_date')) {
            $bill->disconnection_date = $disconnectionDate->format('Y-m-d H:i:s');
        }
        $bill->created_at = $billPeriodTo;
        $bill->saveQuietly();

        $reading->created_at = $billPeriodTo;
        $reading->updated_at = $billPeriodTo;
        $reading->saveQuietly();
    }

    /**
     * Preview dates that would be written to bill + reading after merge (same rules as applyZoneReadingDatesToMergedBill).
     *
     * @return array{resolved: bool, reason?: string, zone_id?: int, bill_period_from?: string, bill_period_to?: string, due_date?: string, penalty_date?: string, disconnection_date?: string, reading_created_at?: string, bill_created_at?: string}
     */
    public function previewStoredDatesForAccount(object $account): array
    {
        $zone = Zone::where('zone', $account->zone ?? '')->first();
        if (!$zone) {
            return [
                'resolved' => false,
                'reason' => 'no_zone',
            ];
        }

        $readingDate = ReadingDate::where('zone_id', $zone->id)
            ->where('is_active', 1)
            ->first();

        if (!$readingDate) {
            return [
                'resolved' => false,
                'reason' => 'no_active_reading_date',
                'zone_id' => $zone->id,
            ];
        }

        $billPeriodFrom = Carbon::parse($readingDate->bill_period_from);
        $billPeriodTo = Carbon::parse($readingDate->bill_period_to);
        $dueDate = Carbon::parse($readingDate->due_date);
        $penaltyDate = $dueDate->copy()->addDay();
        $disconnectionDate = $dueDate->copy()->addDays(7);

        return [
            'resolved' => true,
            'bill_period_from' => $billPeriodFrom->format('Y-m-d H:i:s'),
            'bill_period_to' => $billPeriodTo->format('Y-m-d H:i:s'),
            'due_date' => $dueDate->format('Y-m-d H:i:s'),
            'penalty_date' => $penaltyDate->format('Y-m-d H:i:s'),
            'disconnection_date' => $disconnectionDate->format('Y-m-d H:i:s'),
            'reading_created_at' => $billPeriodTo->format('Y-m-d H:i:s'),
            'bill_created_at' => $billPeriodTo->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Preview the `date` passed to MeterService::create_breakdown (matches OfflineSyncController merge).
     */
    public function previewCreateBreakdownDate(ReadingOffline $off, object $account): string
    {
        $mergeBillingDate = $off->created_at ? Carbon::parse($off->created_at) : now();
        $zone = Zone::where('zone', $account->zone)->first();
        if ($zone) {
            $readingDateRow = ReadingDate::where('zone_id', $zone->id)
                ->where('is_active', 1)
                ->first();
            if ($readingDateRow && !empty($readingDateRow->bill_period_to)) {
                $mergeBillingDate = Carbon::parse($readingDateRow->bill_period_to);
            }
        }

        return $mergeBillingDate->format('Y-m-d H:i:s');
    }
}
