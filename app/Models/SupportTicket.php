<?php

namespace App\Models;

use App\Models\TicketsCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $table = 'support_tickets';
    protected $fillable = [
        'user_id',
        'ticket_no',
        'status',
        'prioritization',
        'category_id',
        'message',
        'feedback',
        'assisted_by',
        'isDeleted'
    ];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function ticket_category() {
        return $this->belongsTo(TicketsCategory::class,'category_id','id');
    }

}