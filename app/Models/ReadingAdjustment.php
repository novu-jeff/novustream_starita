<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReadingAdjustment extends Model
{
    protected $fillable = [
        'reading_id',
        'old_present_reading',
        'new_present_reading',
        'old_consumption',
        'new_consumption',
        'reason',
        'adjusted_by',
        'approved_by',
    ];
}
