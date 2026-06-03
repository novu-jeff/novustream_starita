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
        'status',
        'synced_at',
        'merged_into_reading_id',
        'payload',
    ];

    protected $casts = [
        'previous_reading' => 'integer',
        'present_reading'  => 'integer',
        'consumption'      => 'integer',
        'synced_at'        => 'datetime',
        'payload'          => 'array',
    ];

    public function mergedReading()
    {
        return $this->belongsTo(Reading::class, 'merged_into_reading_id', 'id');
    }

    /**
     * Offline rows still in the merge queue (pending or failed merge retry).
     */
    public function scopeEligibleForMerge($query)
    {
        return $query
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereIn('status', ['pending', 'rejected']);
            })
            ->whereNull('synced_at')
            ->whereNull('merged_into_reading_id');
    }
}
