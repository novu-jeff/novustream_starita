<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaterRates extends Model
{
    use HasFactory;

    protected $table = 'water_rates';
    
    protected $fillable = [
        'property_types_id',
        'cubic_from',
        'cubic_to',
        'rates',
    ];

    public function property_type() {
        return $this->hasOne(PropertyTypes::class, 'id', 'property_types_id');
    }

}
