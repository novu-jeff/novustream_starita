<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartialPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reading_id',
        'partial_payment',
        'remaining_balance',
        'date',
    ];
}
