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
        'contact_no',
        'name',
        'email',
        'senior_citizen_no',
        'pwd_no',
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

    public function accounts() {
        return $this->hasMany(UserAccounts::class);
    }
}