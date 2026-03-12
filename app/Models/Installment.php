<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{

    protected $fillable = [
        'bill_id',
        'user_id',
        'bill_amount',
        'months',
        'monthly_amount',
        'status'
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function schedules()
    {
        return $this->hasMany(InstallmentSchedule::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'name');
    }
}
