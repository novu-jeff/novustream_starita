<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $table = 'zones';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function concessionerAccounts()
    {
        return $this->hasMany(ConcessionerAccount::class, 'zone', 'zone');
    }

    public function accounts()
    {
        return $this->hasMany(ConcessionerAccount::class, 'zone', 'zone');
    }

}
