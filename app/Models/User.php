<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotice;
use App\Notifications\VerifyEmailNotice;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory, Notifiable, CanResetPassword, MustVerifyEmail;

    protected $fillable = [
        'contact_no',
        'name',
        'registrants',
        'email',
        'user_type',
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

    public function accountLinks()
    {
        return $this->hasMany(ConcessionerAccountLink::class, 'user_id');
    }

    public function serviceApplications() {
        return $this->hasMany(ServiceApplication::class);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotice);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotice($token));
    }

}
