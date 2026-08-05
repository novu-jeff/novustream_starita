<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallmentAdjustment extends Model
{
    protected $fillable = [
        'installment_id',
        'bill_id',
        'action',
        'old_data',
        'new_data',
        'reason',
        'adjusted_by',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function installment()
    {
        return $this->belongsTo(Installment::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
