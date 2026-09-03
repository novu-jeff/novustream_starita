<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bill extends Model
{
    use HasFactory;

    protected $table = 'bill';
    protected $fillable = [
        'reading_id',
        'reference_no',
        'payment_id',
        'bill_period_from',
        'bill_period_to',
        'previous_unpaid',
        'total',
        'discount',
        'penalty',
        'amount',
        'amount_after_due',
        'amount_paid',
        'change',
        'isPaid',
        'isInstallment',
        'hasPenalty',
        'hasDisconnection',
        'hasDisconnected',
        'date_paid',
        'due_date',
        'penalty_date',
        'disconnection_date',
        'payor_name',
        'payment_method',
        'paid_by_reference_no',
        'cashier_id',
        'isChangeForAdvancePayment',
        'high_consumption_note',
        'partial_payment',
        'isPartial',
        'hitpay_reference',
        'hitpay_payment_id',
        'initiated_at',
    ];

    protected $casts = [
        'isPaid' => 'boolean',
        'isInstallment' => 'boolean',
        'hasPenalty' => 'boolean',
        'hasDisconnection' => 'boolean',
        'hasDisconnected' => 'boolean',
        'isChangeForAdvancePayment' => 'boolean',
        'isHighConsumption' => 'boolean',
        'isPartial' => 'boolean',
    ];

    public function reading() {
        return $this->hasOne(Reading::class, 'id', 'reading_id');
    }

    public function breakdown() {
        return $this->hasMany(BillBreakdown::class, 'bill_id', 'id');
    }

    public function discount() {
        return $this->hasMany(BillDiscount::class, 'bill_id', 'id');
    }

    public function client()
    {
        return $this->hasOneThrough(
            User::class,
            ConcessionerAccount::class,
            'account_no',       // Foreign key on ConcessionerAccount
            'id',               // Foreign key on User
            'reading_id',       // Local key on Bill (via Reading relationship)
            'user_id'           // Local key on ConcessionerAccount
        );
    }

    public function installment()
    {
        return $this->hasOne(Installment::class);
    }

    public function adjustments()
    {
        return $this->hasMany(BillAdjustment::class)->latest();
    }

    public function creditedPartialAmount(): float
    {
        return self::creditedPartialFromValues(
            $this->partial_payment,
            $this->isPartial,
            $this->amount_paid
        );
    }

    /**
     * Remaining amount due after a partial payment.
     * `amount` is the original billed total; cashiers do not reduce it when recording a partial.
     */
    public function netUnpaidAmount(): float
    {
        return self::netUnpaidFromValues(
            $this->isPaid,
            $this->amount,
            $this->partial_payment,
            $this->isPartial,
            $this->amount_paid
        );
    }

    public static function creditedPartialFromValues(mixed $partialPayment, mixed $isPartial, mixed $amountPaid): float
    {
        $partial = (float) ($partialPayment ?? 0);
        if ($partial <= 0 && filter_var($isPartial, FILTER_VALIDATE_BOOLEAN)) {
            $partial = (float) ($amountPaid ?? 0);
        }

        return max($partial, 0);
    }

    public static function netUnpaidFromValues(mixed $isPaid, mixed $amount, mixed $partialPayment, mixed $isPartial, mixed $amountPaid): float
    {
        if (filter_var($isPaid, FILTER_VALIDATE_BOOLEAN)) {
            return 0.0;
        }

        return max((float) ($amount ?? 0) - self::creditedPartialFromValues($partialPayment, $isPartial, $amountPaid), 0);
    }

}
