<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ruling extends Model
{
    use HasFactory;

    protected $table = 'global_ruling';
    protected $fillable = [
        'due_date',
        'disconnection_date',
        'disconnection_rule'
    ];

}
