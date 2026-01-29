<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type', // deposit, payment, refund, admin_deposit
        'amount',
        'service_code',
        'status', // pending, success, failed
        'ref',
        'remote_ref',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
