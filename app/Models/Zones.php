<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zones extends Model
{
    use HasFactory;
    protected $table = 'zones';
    protected $guarded = ['id', 'created_at', 'updated_at'];
}
