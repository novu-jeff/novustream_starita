<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenaltyExemption extends Model
{
    use HasFactory;

    protected $fillable = [
    'account_no',
    'id_no',
    'penalty_exemption_type_id',
    'effective_date',
    'expired_date',
];

public function type()
{
    return $this->belongsTo(PenaltyExemptionType::class, 'penalty_exemption_type_id');
}

public function account()
{
    return $this->belongsTo(
        \App\Models\ConcessionerAccount::class,
        'account_no',
        'account_no'
    );
}

}
