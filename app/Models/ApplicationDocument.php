<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationDocument extends Model
{
    protected $fillable = [
        'service_application_id',
        'valid_id',
        'cedula',
        'proof_of_billing',
        'authorization_letter',
        'proof_of_ownership',
        'tax_declaration',
        'barangay_clearance',
        'others',
    ];

    public function serviceApplication()
    {
        return $this->belongsTo(ServiceApplication::class);
    }
}
