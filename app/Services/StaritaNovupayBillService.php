<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\NovupayStaritaBill;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaritaNovupayBillService
{
    /**
     * Resolve which local bill should receive a Novupay / online payment.
     *
     * The SOA / HitPay reference is authoritative. Never retarget a payment onto
     * a later bill just because paid_at falls in that bill's calendar month —
     * that copied August payments onto the next month's SOA.
     */
    public function resolveLocalBillForPayment(NovupayStaritaBill $nb, ?Bill $billByReference = null): ?Bill
    {
        $referenceNo = trim((string) ($nb->reference_no ?? ''));
        $accountNo = trim((string) ($nb->account_no ?? ''));

        $localBill = $billByReference ?? ($referenceNo !== '' ? Bill::where('reference_no', $referenceNo)->first() : null);

        if ($localBill) {
            return $localBill;
        }

        $matched = $this->findUnpaidBillMatchingPayment($accountNo, $nb);
        if ($matched && !$this->paymentWouldMisapply($matched, $nb)) {
            return $matched;
        }

        $oldestUnpaid = $accountNo !== '' ? $this->findOldestUnpaidBillForAccount($accountNo) : null;
        if ($oldestUnpaid && !$this->paymentWouldMisapply($oldestUnpaid, $nb)) {
            return $oldestUnpaid;
        }

        return $matched;
    }

    public function findOldestUnpaidBillForAccount(string $accountNo): ?Bill
    {
        if ($accountNo === '') {
            return null;
        }

        return Bill::query()
            ->with('reading')
            ->whereHas('reading', function ($q) use ($accountNo) {
                $q->where('account_no', $accountNo);
            })
            ->where('isPaid', false)
            ->orderBy('bill_period_from')
            ->orderBy('id')
            ->first();
    }

    /**
     * Match an unpaid bill to the payment using present reading, then SOA amount.
     */
    public function findUnpaidBillMatchingPayment(string $accountNo, NovupayStaritaBill $nb): ?Bill
    {
        if ($accountNo === '') {
            return null;
        }

        $unpaid = Bill::query()
            ->with('reading')
            ->whereHas('reading', function ($q) use ($accountNo) {
                $q->where('account_no', $accountNo);
            })
            ->where('isPaid', false)
            ->orderByDesc('bill_period_to')
            ->orderByDesc('id')
            ->get();

        if ($unpaid->isEmpty()) {
            return null;
        }

        $present = $this->novupayPresentReading($nb);
        if ($present > 0) {
            $byReading = $unpaid->first(function (Bill $bill) use ($present) {
                return (int) optional($bill->reading)->present_reading === $present;
            });
            if ($byReading) {
                return $byReading;
            }

            // Payment is tied to a specific meter state. Do not amount-match a later bill
            // that happens to have the same minimum charge (e.g. 160).
            return null;
        }

        $amount = $this->novupayBillAmount($nb);
        if ($amount > 0) {
            $byAmount = $unpaid->first(function (Bill $bill) use ($amount) {
                return $this->billAmountMatchesPayment($bill, $amount);
            });
            if ($byAmount) {
                return $byAmount;
            }
        }

        return null;
    }

    public function billAmountMatchesPayment(Bill $bill, float $amount): bool
    {
        $amount = round($amount, 2);
        $candidates = array_unique(array_filter([
            round((float) ($bill->total ?? 0), 2),
            round(max((float) ($bill->total ?? 0) - BillSettlementService::numericBillAttribute($bill, 'discount'), 0), 2),
            round((float) ($bill->amount ?? 0), 2),
            round((float) ($bill->amount_after_due ?? 0), 2),
        ], fn ($value) => $value > 0));

        foreach ($candidates as $candidate) {
            if (abs($candidate - $amount) < 0.06) {
                return true;
            }
            if (BillSettlementService::looksLikeCheckoutTotal($candidate, $amount)) {
                return true;
            }
        }

        return false;
    }

    public function novupayBillAmount(NovupayStaritaBill $nb): float
    {
        $raw = $nb->amount
            ?? data_get($nb->payload, 'purpose_amount')
            ?? 0;

        if (is_string($raw)) {
            $raw = str_replace([',', '₱', ' '], '', $raw);
        }

        $amount = round((float) $raw, 2);
        if ($amount > 0) {
            return $amount;
        }

        $purpose = (string) data_get($nb->payload, 'purpose', '');
        if (preg_match('/Amount Due:\s*₱?\s*([\d,]+(?:\.\d+)?)/u', $purpose, $m)) {
            return round((float) str_replace(',', '', $m[1]), 2);
        }

        return 0.0;
    }

    /**
     * Match by bill_period_to month/year (canonical billing period), not reading.created_at.
     */
    public function findBillByBillingPeriod(string $accountNo, NovupayStaritaBill $nb): ?Bill
    {
        if ($accountNo === '') {
            return null;
        }

        $sourceDate = $nb->paid_at
            ?? $nb->initiated_at
            ?? $nb->created_at
            ?? now();

        $parsed = Carbon::parse($sourceDate);

        $candidates = Bill::query()
            ->whereHas('reading', function ($q) use ($accountNo) {
                $q->where('account_no', $accountNo);
            })
            ->whereYear('bill_period_to', $parsed->year)
            ->whereMonth('bill_period_to', $parsed->month)
            ->orderByDesc('id')
            ->get();

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($candidates->count() > 1) {
            $unpaid = $candidates->firstWhere('isPaid', false);
            if ($unpaid) {
                return $unpaid;
            }
        }

        return null;
    }

    /**
     * Paid Novupay rows applied to the wrong local bill (missing QR ref, or oldest-unpaid).
     * Do not re-apply a payment that is already on its own reference bill.
     */
    public function needsMisappliedPaymentRepair(NovupayStaritaBill $nb): bool
    {
        $accountNo = trim((string) ($nb->account_no ?? ''));
        if ($accountNo === '' || !$nb->paid_at) {
            return false;
        }

        $referenceNo = trim((string) ($nb->reference_no ?? ''));
        $referenceBill = $referenceNo !== ''
            ? Bill::where('reference_no', $referenceNo)->first()
            : null;

        if ($referenceBill && $referenceBill->isPaid && $this->billAlreadyHasThisPayment($referenceBill, $nb)) {
            return false;
        }

        $matched = $this->findUnpaidBillMatchingPayment($accountNo, $nb);
        if (!$matched) {
            return false;
        }

        if ($referenceBill && (int) $referenceBill->id === (int) $matched->id) {
            return false;
        }

        return true;
    }

    public function billAlreadyHasThisPayment(Bill $bill, NovupayStaritaBill $nb): bool
    {
        if (!$bill->isPaid) {
            return false;
        }

        $ref = trim((string) ($nb->reference_no ?? ''));
        $hitpay = trim((string) ($nb->hitpay_reference ?? ''));

        if ($ref !== '' && in_array($ref, [
            (string) $bill->reference_no,
            (string) $bill->hitpay_reference,
            (string) $bill->hitpay_payment_id,
        ], true)) {
            return true;
        }

        if ($hitpay !== '' && in_array($hitpay, [
            (string) $bill->hitpay_reference,
            (string) $bill->hitpay_payment_id,
        ], true)) {
            return true;
        }

        return false;
    }

    /**
     * True when applying this Novupay payment would stamp an older payment onto a later SOA.
     */
    public function paymentWouldMisapply(Bill $bill, NovupayStaritaBill $nb): bool
    {
        $ref = trim((string) ($nb->reference_no ?? ''));
        if ($ref !== '' && $ref === trim((string) ($bill->reference_no ?? ''))) {
            return false;
        }

        $paidAt = $nb->paid_at ?? $nb->initiated_at ?? $nb->created_at ?? null;
        if ($paidAt && $this->paymentDateIsBeforeBillPeriod($bill, $paidAt)) {
            return true;
        }

        $hitpay = trim((string) ($nb->hitpay_reference ?? ''));
        if ($hitpay !== '' && $this->isForeignSoaReference($hitpay, (string) ($bill->reference_no ?? ''))) {
            return true;
        }

        $amount = $this->novupayBillAmount($nb);
        if ($amount > 0 && !$this->billAmountMatchesPayment($bill, $amount)) {
            return true;
        }

        return false;
    }

    public function paymentDateIsBeforeBillPeriod(Bill $bill, $paidAt): bool
    {
        try {
            $paid = Carbon::parse($paidAt);
        } catch (\Throwable $e) {
            return false;
        }

        if (!empty($bill->bill_period_from)) {
            try {
                if ($paid->lt(Carbon::parse($bill->bill_period_from)->startOfDay())) {
                    return true;
                }
            } catch (\Throwable $e) {
                // ignore unparseable period
            }
        }

        if (!empty($bill->created_at)) {
            try {
                if ($paid->lt(Carbon::parse($bill->created_at)->startOfDay())) {
                    return true;
                }
            } catch (\Throwable $e) {
                // ignore unparseable created_at
            }
        }

        return false;
    }

    public function isForeignSoaReference(string $hitpayReference, string $billReference): bool
    {
        $hitpay = trim($hitpayReference);
        $billRef = trim($billReference);
        if ($hitpay === '' || $billRef === '' || strcasecmp($hitpay, $billRef) === 0) {
            return false;
        }

        return (bool) preg_match('/NST-SRWD-/i', $hitpay);
    }

    public function novupayPresentReading(NovupayStaritaBill $nb): int
    {
        $column = (int) ($nb->present_reading ?? 0);
        if ($column > 0) {
            return $column;
        }

        return (int) (data_get($nb->payload, 'present_reading') ?: 0);
    }

    public static function isPlaceholderPayor(?string $name): bool
    {
        $trimmed = strtolower(trim((string) $name));
        if ($trimmed === '') {
            return true;
        }

        return in_array($trimmed, ['unknown', 'n/a', 'na', 'null', '-', 'none'], true);
    }

    public static function firstUsablePayor(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (!self::isPlaceholderPayor($candidate)) {
                return trim((string) $candidate);
            }
        }

        return null;
    }

    /**
     * Upsert starita_bills from a locally settled online bill (direct HitPay path).
     */
    public function upsertFromLocalBill(Bill $bill): void
    {
        $connection = (new NovupayStaritaBill())->getConnectionName();
        if (!Schema::connection($connection)->hasTable('starita_bills')) {
            return;
        }

        $bill->loadMissing('reading');
        $reading = $bill->reading;
        if (!$reading) {
            return;
        }

        $accountNo = trim((string) ($reading->account_no ?? ''));
        $referenceNo = trim((string) ($bill->reference_no ?? ''));
        if ($accountNo === '' || $referenceNo === '') {
            return;
        }

        $paidAt = $bill->date_paid ? Carbon::parse($bill->date_paid) : now();
        $initiatedAt = $bill->initiated_at ? Carbon::parse($bill->initiated_at) : ($bill->created_at ? Carbon::parse($bill->created_at) : now());

        $payload = [
            'customer' => ['name' => $bill->payor_name ?? ''],
            'payor' => $bill->payor_name ?? '',
            'reference_no' => $referenceNo,
            'source' => 'sta_rita_hitpay',
            'previous_reading' => (int) ($reading->previous_reading ?? 0),
            'present_reading' => (int) ($reading->present_reading ?? 0),
        ];

        $row = [
            'account_no' => $accountNo,
            'payor' => $bill->payor_name ?? null,
            'amount' => (float) ($bill->isPaid && $bill->amount_paid !== null && $bill->amount_paid !== ''
                ? $bill->amount_paid
                : (new BillSettlementService())->inferSettledAmount($bill)),
            'status' => 'paid',
            'payload' => $payload,
            'initiated_at' => $initiatedAt,
            'paid_at' => $paidAt,
            'synced_to_sta_rita_at' => now(),
        ];

        $columns = Schema::connection($connection)->getColumnListing('starita_bills');
        if (in_array('hitpay_reference', $columns, true) && !empty($bill->hitpay_reference)) {
            $row['hitpay_reference'] = $bill->hitpay_reference;
        }
        if (in_array('previous_reading', $columns, true)) {
            $row['previous_reading'] = (int) ($reading->previous_reading ?? 0);
        }
        if (in_array('present_reading', $columns, true)) {
            $row['present_reading'] = (int) ($reading->present_reading ?? 0);
        }
        if (in_array('is_high_consumption', $columns, true)) {
            $row['is_high_consumption'] = (bool) ($bill->isHighConsumption ?? false);
        }

        $existing = NovupayStaritaBill::where('reference_no', $referenceNo)->first();
        if ($existing) {
            // Never downgrade a paid Novupay row; only enrich missing fields.
            if (strtolower((string) $existing->status) === 'paid' || $existing->paid_at) {
                unset($row['status'], $row['paid_at']);
            }
            NovupayStaritaBill::whereKey($existing->id)->update($row);
            return;
        }

        $row['reference_no'] = $referenceNo;
        NovupayStaritaBill::create($row);
    }

    /**
     * Backfill starita_bills rows for direct HitPay payments missing from Novupay DB.
     *
     * @return int Number of rows inserted
     */
    public function backfillMissingFromLocalBills(int $limit = 50): int
    {
        $connection = (new NovupayStaritaBill())->getConnectionName();
        if (!Schema::connection($connection)->hasTable('starita_bills')) {
            return 0;
        }

        $bills = Bill::query()
            ->where('payment_method', 'online')
            ->where('isPaid', true)
            ->whereNotNull('reference_no')
            ->where('reference_no', '!=', '')
            ->with('reading')
            ->orderByDesc('updated_at')
            ->limit(max($limit * 4, 200))
            ->get();

        if ($bills->isEmpty()) {
            return 0;
        }

        $existingRefs = NovupayStaritaBill::query()
            ->whereIn('reference_no', $bills->pluck('reference_no')->unique()->filter()->values()->all())
            ->pluck('reference_no')
            ->flip()
            ->all();

        $inserted = 0;
        foreach ($bills as $bill) {
            if ($inserted >= $limit) {
                break;
            }
            if (isset($existingRefs[$bill->reference_no])) {
                continue;
            }
            $this->upsertFromLocalBill($bill);
            $inserted++;
        }

        return $inserted;
    }
}
