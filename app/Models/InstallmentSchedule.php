<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstallmentSchedule extends Model
{

    protected $fillable = [
        'installment_id',
        'month_no',
        'amount',
        'due_date',
        'is_paid',
        'paid_at'
    ];

    public function installment()
    {
        return $this->belongsTo(Installment::class);
    }

}
