<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterBill extends Model
{
    use HasFactory;
    
    protected $table = 'water_bill';
    protected $fillable = [
        'reference_no',
        'water_reading_id',
        'amount',
        'amount_paid',
        'isPaid',
        'date_paid'
    ];

    public function reading() {
        return $this->hasOne(WaterReading::class, 'id', 'water_reading_id');
    }

}
