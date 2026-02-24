<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenaltyExemptionType extends Model
{
    use HasFactory;

    protected $table = 'penalty_exemption_type';

    protected $fillable = [
        'penalty_exemption_name'
    ];
}
