<?php

namespace App\Services;

use App\Models\Reading;
use App\Models\ReadingDate;
use App\Models\ReadingOffline;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Duplicate / stale-row rules for readings_offline → readings merge.
 *
 * Duplicate detection must use the target billing period (zone ReadingDate
 * bill_period_to), not the offline row's created_at. Mobile re-uploads of an
 * already-billed meter state often arrive in a later calendar month.
 */
class OfflineMergeGuard
{
    /**
     * Date written to the merged reading/bill (same rule as OfflineSyncController).
     */
    public function resolveMergeBillingDate(ReadingOffline $off, ?object $account = null): Carbon
    {
        $mergeBillingDate = $off->created_at ? Carbon::parse($off->created_at) : now();
        $zoneCode = $account->zone ?? $off->zone ?? null;
        if (!$zoneCode) {
            return $mergeBillingDate;
        }

        $zone = Zone::where('zone', $zoneCode)->first();
        if (!$zone) {
            return $mergeBillingDate;
        }

        $readingDateRow = ReadingDate::where('zone_id', $zone->id)
            ->where('is_active', 1)
            ->first();
        if ($readingDateRow && !empty($readingDateRow->bill_period_to)) {
            return Carbon::parse($readingDateRow->bill_period_to);
        }

        return $mergeBillingDate;
    }

    /**
     * When several pending offline rows share an account, merge the newest meter
     * state (highest present_reading, then highest id) and skip the rest.
     */
    public function pickWinner(Collection $rows): ReadingOffline
    {
        return $rows->sortByDesc(function ($row) {
            return sprintf('%020d-%020d', (int) $row->present_reading, (int) $row->id);
        })->first();
    }

    public function isSameMeterState(Reading $reading, ReadingOffline $off): bool
    {
        return (int) $reading->previous_reading === (int) $off->previous_reading
            && (int) $reading->present_reading === (int) $off->present_reading;
    }

    /**
     * Existing merged reading that means this offline row must not create another bill.
     *
     * - Same account already billed for the merge billing month (created_at or bill_period_to)
     * - Latest reading already has this exact previous+present and that cycle had consumption
     *   (re-upload of last billed cycle). Consecutive 0-consumption months are allowed.
     * - Offline present is lower than the latest billed present (stale leftover)
     */
    public function findConflictingReading(ReadingOffline $off, ?Carbon $mergeBillingDate = null): ?Reading
    {
        $isReRead = filter_var($off->payload['isReRead'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $mergeBillingDate = $mergeBillingDate ?? $this->resolveMergeBillingDate($off);

        if (!$isReRead) {
            $year = $mergeBillingDate->year;
            $month = $mergeBillingDate->month;
            $existing = Reading::where('account_no', $off->account_no)
                ->where('isReRead', false)
                ->where(function ($q) use ($year, $month) {
                    $q->where(function ($q2) use ($year, $month) {
                        $q2->whereYear('created_at', $year)
                            ->whereMonth('created_at', $month);
                    })->orWhereHas('bill', function ($bq) use ($year, $month) {
                        $bq->whereYear('bill_period_to', $year)
                            ->whereMonth('bill_period_to', $month);
                    });
                })
                ->orderByDesc('id')
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $latest = Reading::where('account_no', $off->account_no)
            ->where('isReRead', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if (!$latest) {
            return null;
        }

        if ($this->isSameMeterState($latest, $off) && (int) $latest->present_reading !== (int) $latest->previous_reading) {
            return $latest;
        }

        if ((int) $off->present_reading < (int) $latest->present_reading) {
            return $latest;
        }

        return null;
    }
}
