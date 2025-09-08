<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rates extends Model
{
    use HasFactory;

    protected $table = 'rates';

   protected $guarded = ['id', 'created_at', 'updated_at'];

    public function property_type() {
        return $this->hasOne(PropertyTypes::class, 'id', 'property_types_id');
    }

}
