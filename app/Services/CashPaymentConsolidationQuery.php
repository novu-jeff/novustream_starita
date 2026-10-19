<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CashPaymentConsolidationQuery
{
    public static function build(
        string $period,
        string $search = '',
        ?string $fromDate = null,
        ?string $toDate = null
    ): Builder
    {
        [$start, $end] = self::dateRange($period, $fromDate, $toDate);

        $query = DB::table('bill')
            ->leftJoin('readings', 'bill.reading_id', '=', 'readings.id')
            ->leftJoin('concessioner_accounts as ca', 'readings.account_no', '=', 'ca.account_no')
            ->leftJoin('users', 'ca.user_id', '=', 'users.id')
            ->where('bill.payment_method', 'cash')
            ->where(function (Builder $query) {
                $query->where('bill.isPaid', 1)
                    ->orWhere('bill.isPartial', 1);
            })
            ->whereBetween('bill.date_paid', [$start, $end])
            ->select([
                'bill.id',
                'users.name',
                'ca.account_no',
                'bill.reference_no',
                'ca.property_type',
                'bill.amount_paid',
                'bill.date_paid',
            ]);

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->where(function (Builder $query) use ($like) {
                $query->where('users.name', 'like', $like)
                    ->orWhere('ca.account_no', 'like', $like)
                    ->orWhere('bill.reference_no', 'like', $like);
            });
        }

        return $query
            ->orderByDesc('bill.date_paid')
            ->orderByDesc('bill.id');
    }

    public static function findReceipt(int $billId): ?object
    {
        return DB::table('bill')
            ->leftJoin('readings', 'bill.reading_id', '=', 'readings.id')
            ->leftJoin('concessioner_accounts as ca', 'readings.account_no', '=', 'ca.account_no')
            ->leftJoin('users', 'ca.user_id', '=', 'users.id')
            ->where('bill.id', $billId)
            ->where('bill.payment_method', 'cash')
            ->where(function (Builder $query) {
                $query->where('bill.isPaid', 1)
                    ->orWhere('bill.isPartial', 1);
            })
            ->select([
                'bill.id',
                'users.name',
                'ca.account_no',
                'bill.reference_no',
                'ca.property_type',
                'bill.amount_paid',
                'bill.date_paid',
            ])
            ->first();
    }

    private static function dateRange(string $period, ?string $fromDate, ?string $toDate): array
    {
        if ($period === 'custom' && $fromDate !== null && $toDate !== null) {
            return [
                Carbon::parse($fromDate, 'Asia/Manila')->startOfDay()->format('Y-m-d'),
                Carbon::parse($toDate, 'Asia/Manila')->endOfDay()->format('Y-m-d H:i:s'),
            ];
        }

        $today = Carbon::now('Asia/Manila')->startOfDay();

        if ($period === 'week') {
            $start = $today->copy()->startOfWeek(Carbon::MONDAY);
            $end = $today->copy()->endOfWeek(Carbon::SUNDAY);
        } elseif ($period === 'month') {
            $start = $today->copy()->startOfMonth();
            $end = $today->copy()->endOfMonth();
        } else {
            $start = $today;
            $end = $today->copy();
        }

        return [
            $start->format('Y-m-d'),
            $end->format('Y-m-d 23:59:59'),
        ];
    }
}