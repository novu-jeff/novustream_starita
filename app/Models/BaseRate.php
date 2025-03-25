<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaseRate extends Model
{
    use HasFactory;

    protected $table = 'base_rate';

    protected $guarded = ['id', 'created_at', 'updated_at'];
}
