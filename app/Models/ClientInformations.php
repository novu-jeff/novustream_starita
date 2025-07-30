<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientInformations extends Model
{
    use HasFactory;

    protected $table = 'client_informations';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    
}
