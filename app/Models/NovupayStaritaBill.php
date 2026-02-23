<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Novupay-originated bills stored in sta-rita DB (when customer scans Starita QR).
 * Table: starita_bills. Use reference_no for idempotent sync to readings + bill.
 */
class NovupayStaritaBill extends Model
{
    protected $connection = 'novupay_starita';
    protected $table = 'starita_bills';

    protected $fillable = [
        'reference_no', 'account_no', 'payor', 'amount', 'previous_reading', 'present_reading',
        'is_high_consumption', 'status', 'payload', 'initiated_at', 'paid_at',
        'hitpay_reference', 'synced_to_sta_rita_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'previous_reading' => 'integer',
        'present_reading' => 'integer',
        'is_high_consumption' => 'boolean',
        'payload' => 'array',
        'initiated_at' => 'datetime',
        'paid_at' => 'datetime',
        'synced_to_sta_rita_at' => 'datetime',
    ];
}
