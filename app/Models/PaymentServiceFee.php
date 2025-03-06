<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentServiceFee extends Model
{
    use HasFactory;

    protected $table = 'payment_service_fees';
    protected $fillable = [
        'property_id',
        'amount'
    ];

    public function property() {
        return $this->hasOne(PropertyTypes::class, 'id', 'property_id');
    }

}
