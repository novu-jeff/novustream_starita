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
     * When the QR reference bill is already paid, apply to the oldest unpaid bill instead.
     */
    public function resolveLocalBillForPayment(NovupayStaritaBill $nb, ?Bill $billByReference = null): ?Bill
    {
        $referenceNo = trim((string) ($nb->reference_no ?? ''));
        $accountNo = trim((string) ($nb->account_no ?? ''));

        $localBill = $billByReference ?? ($referenceNo !== '' ? Bill::where('reference_no', $referenceNo)->first() : null);

        if ($localBill && !$localBill->isPaid) {
            return $localBill;
        }

        if ($accountNo !== '') {
            $oldestUnpaid = $this->findOldestUnpaidBillForAccount($accountNo);
            if ($oldestUnpaid) {
                return $oldestUnpaid;
            }
        }

        if ($localBill) {
            return $localBill;
        }

        return $this->findBillByBillingPeriod($accountNo, $nb);
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
     * Paid Novupay rows synced to an already-paid reference while newer bills remain unpaid.
     */
    public function needsMisappliedPaymentRepair(NovupayStaritaBill $nb): bool
    {
        $accountNo = trim((string) ($nb->account_no ?? ''));
        $referenceNo = trim((string) ($nb->reference_no ?? ''));
        if ($accountNo === '' || $referenceNo === '' || !$nb->paid_at) {
            return false;
        }

        $referenceBill = Bill::where('reference_no', $referenceNo)->first();
        if (!$referenceBill || !$referenceBill->isPaid) {
            return false;
        }

        if (!$this->findOldestUnpaidBillForAccount($accountNo)) {
            return false;
        }

        $referencePaidAt = $referenceBill->date_paid
            ? Carbon::parse($referenceBill->date_paid)
            : null;

        if ($referencePaidAt === null) {
            return true;
        }

        return Carbon::parse($nb->paid_at)->gt($referencePaidAt);
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
            'amount' => (float) ($bill->amount ?? 0),
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
