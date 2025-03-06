<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterBillBreakdown extends Model
{
    use HasFactory;

    protected $table = 'water_bill_breakdown';
    protected $fillable = [
        'water_bill_id',
        'name',
        'description',
        'amount'
    ];

}
