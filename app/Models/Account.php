<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function zone()
    {
        return $this->hasMany(ConcessionerAccount::class, 'zone', 'id');
    }

    public function readings()
    {
        return $this->hasMany(Reading::class, 'account_id');
    }
    public function propertyType()
    {
        return $this->belongsTo(PropertyTypes::class, 'property_type_id');
    }

}
