<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Statistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'total_ads',
        'active_ads',
        'pending_ads',
        'new_users',
        'total_users',
        'page_views',
        'ad_views',
        'messages_sent',
        'revenue',
    ];

    protected $casts = [
        'date' => 'date',
        'revenue' => 'decimal:2',
    ];
}
