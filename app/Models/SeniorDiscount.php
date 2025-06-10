<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeniorDiscount extends Model
{
    use HasFactory;

    protected $table = 'senior_discount';
    protected $fillable = [
        'account_no',
        'id_no',
        'effective_date',
        'expired_date'
    ];

}
