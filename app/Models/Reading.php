<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reading extends Model
{
    use HasFactory;

    protected $table = 'readings';
    protected $fillable = [
        'meter_no',
        'previous_reading',
        'present_reading',
        'consumption',
    ];

    public function user() {
        return $this->hasOne(User::class, 'meter_serial_no', 'meter_no');
    }

    public function bill() {
        return $this->hasOne(Bill::class, 'reading_id', 'id');
    }

}
