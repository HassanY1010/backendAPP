<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'plan_name',
        'price',
        'duration_days',
        'max_ads',
        'features',
        'starts_at',
        'ends_at',
        'is_active',
        'auto_renew',
    ];

    protected $casts = [
        'features' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'auto_renew' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Optional: if plan is deleted, we still have plan_name/price snapshot, but can link to soft-deleted plan if needed
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
