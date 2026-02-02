<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingOffline extends Model
{
    protected $table = 'readings_offline';

    protected $fillable = [
        'reference_no',
        'account_no',
        'previous_reading',
        'present_reading',
        'consumption',
        'reader_name',
        'zone',
        'source',
        'synced_at',
        'merged_into_reading_id',
        'payload',
    ];

    protected $casts = [
        'previous_reading' => 'decimal:4',
        'present_reading'  => 'decimal:4',
        'consumption'      => 'decimal:4',
        'synced_at'        => 'datetime',
        'payload'          => 'array',
    ];

    public function mergedReading()
    {
        return $this->belongsTo(Reading::class, 'merged_into_reading_id', 'id');
    }
}
