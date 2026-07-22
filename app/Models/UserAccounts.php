<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAccounts extends Model
{
    use HasFactory;

    protected $table = 'concessioner_accounts';

    protected $guarded = [
        'id',
        'isActive',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'isApproved' => 'boolean',
        'approved_at' => 'datetime',
        'denied_at' => 'datetime',
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function property_types() {
        return $this->hasOne(PropertyTypes::class, 'id', 'property_type');
    }

    public function sc_discount() {
        return $this->hasOne(SeniorDiscount::class, 'account_no', 'account_no');
    }
    public function readings() {
        return $this->hasMany(Reading::class, 'account_no', 'account_no');
    }

    public function discount()
    {
        return $this->hasOne(Discount::class, 'account_no', 'account_no');
    }

    public function property_types_by_name() {
        return $this->hasOne(PropertyTypes::class, 'name', 'property_type');
    }


}
