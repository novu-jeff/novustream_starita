<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterBill extends Model
{
    use HasFactory;

    protected $table = 'water_bill';
    protected $fillable = [
        'water_reading_id',
        'reference_no',
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
        return $this->hasOne(WaterReading::class, 'id', 'water_reading_id');
    }

    public function breakdown() {
        return $this->hasMany(WaterBillBreakdown::class, 'water_bill_id', 'id');
    }

}
