<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConcessionerAccountLink extends Model
{
    use HasFactory;

    protected $table = 'concessioner_account_links';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $casts = [
        'approved_at' => 'datetime',
        'denied_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(UserAccounts::class, 'account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
