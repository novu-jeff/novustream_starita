<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentBreakdownPenalty extends Model
{
    use HasFactory;

    protected $table = 'payment_breakdown_penalty';
    protected $fillable = [
        'due_from',
        'due_to',
        'amount'
    ];

}
