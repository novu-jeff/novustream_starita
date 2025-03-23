<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'account_no',
        'name',
        'address',
        'rate_code',
        'status',
        'meter_brand',
        'meter_serial_no',
        'sc_no',
        'date_connected',
        'contact_no',
        'sequence_no',
        'isValidated',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'isValidated' => 'boolean',
    ];

    public function property_types() {
        return $this->hasOne(PropertyTypes::class, 'id', 'property_type');
    }
}