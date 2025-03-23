<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillBreakdown extends Model
{
    use HasFactory;

    protected $table = 'bill_breakdown';
    protected $fillable = [
        'bill_id',
        'name',
        'description',
        'amount'
    ];

}
