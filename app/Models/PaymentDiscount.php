<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentDiscount extends Model
{
    use HasFactory;

    protected $table = 'payment_discount';
    protected $fillable = [
        'name',
        'eligible',
        'type',
        'percentage_of',
        'amount'
    ];

}
