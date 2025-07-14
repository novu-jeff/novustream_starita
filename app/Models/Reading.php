<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reading extends Model
{
    use HasFactory;

    protected $table = 'readings';
    protected $fillable = [
        'account_no',
        'previous_reading',
        'present_reading',
        'consumption',
        'reader_name',
        'isReRead',
        'reread_reference_no'
    ];

    public function concessionaire() {
        return $this->hasOne(UserAccounts::class, 'account_no', 'account_no');
    }

    public function bill() {
        return $this->hasOne(Bill::class, 'reading_id', 'id');
    }

    public function sc_discount() {
        return $this->hasOne(SeniorDiscount::class, 'account_no', 'account_no');
    }

}
