<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceApplication extends Model
{
    protected $fillable = [
        'user_id',
        'application_no',
        'cellphone',
        'applicant_name',
        'service_address',
        'application_type',
        'application_type_other',
        'connection_type',
        'connection_size',
        'installation_location',
        'property_owner',
        'promissory_note',
        'promissory_amount',
        'application_fee_amount',
        'application_fee_status',
        'status',
    ];

    protected $casts = [
        'promissory_note' => 'boolean',
        'promissory_amount' => 'decimal:2',
        'application_fee_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->hasOne(ApplicationDocument::class);
    }
}
