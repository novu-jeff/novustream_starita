<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterReading extends Model
{
    use HasFactory;

    protected $table = 'water_readings';
    protected $fillable = [
        'meter_no',
        'previous_reading',
        'present_reading',
        'consumption',
    ];

    public function user() {
        return $this->hasOne(User::class, 'meter_no', 'meter_no');
    }

    public function bill() {
        return $this->hasOne(WaterBill::class, 'water_reading_id', 'id');
    }

}
