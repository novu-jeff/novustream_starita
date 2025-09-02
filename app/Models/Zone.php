<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $table = ['zones', 'concessioner_accounts'];
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function concessionerAccounts()
    {
        return $this->hasMany(ConcessionerAccount::class, 'zone', 'zone');
    }

    public function accounts()
    {
        return $this->hasMany(ConcessionerAccount::class, 'zone', 'zone');
        // 'zone' is the foreign key in concessioner_accounts, 'zone' is the local key in Zone
    }
}
