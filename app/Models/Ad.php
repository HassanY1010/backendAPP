<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ad extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'slug',
        'description',
        'price',
        'currency',
        'location',
        'address',
        'latitude',
        'longitude',
        'status',
        'views',
        'is_featured',
        'is_urgent',
        'is_premium',
        'contact_phone',
        'contact_email',
        'contact_whatsapp',
        'is_negotiable',
        'condition',
        'reject_reason',
        'expires_at',
        'featured_until',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_urgent' => 'boolean',
            'is_premium' => 'boolean',
            'is_negotiable' => 'boolean',
            'price' => 'decimal:2',
            'expires_at' => 'datetime',
            'featured_until' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        $cleanup = function ($ad) {
            // Delete associated images (this will trigger AdImage's deleted event to remove files)
            $ad->images()->each(function ($image) {
                $image->delete();
            });

            // Delete associated comments
            $ad->comments()->delete();

            // Detach from favorites
            $ad->favoritedBy()->detach();

            // Delete custom fields
            $ad->customFields()->delete();
        };

        static::deleting($cleanup);
        static::forceDeleting($cleanup);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(AdImage::class)->orderBy('sort_order');
    }

    public function mainImage()
    {
        return $this->hasOne(AdImage::class)->where('is_main', true);
    }

    public function customFields()
    {
        return $this->hasMany(AdCustomField::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites', 'ad_id', 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }



    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }
}
