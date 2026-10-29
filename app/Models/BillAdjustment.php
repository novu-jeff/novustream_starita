<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillAdjustment extends Model
{
    protected $fillable = [
        'bill_id',
        'old_amount',
        'new_amount',
        'old_total',
        'new_total',
        'old_data',
        'new_data',
        'reason',
        'adjusted_by'
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }
}
