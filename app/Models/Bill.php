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
        'amount',
        'amount_paid',
        'isPaid',
        'date_paid',
        'due_date',
        'payor_name',
        'paid_by_reference_no',
    ];

    public function reading() {
        return $this->hasOne(Reading::class, 'id', 'reading_id');
    }

    public function breakdown() {
        return $this->hasMany(BillBreakdown::class, 'bill_id', 'id');
    }

}
