<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdImage extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ad_id',
        'image_path',
        'thumbnail_path',
        'is_main',
        'sort_order',
        'alt_text',
        'created_at',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['image_url', 'thumbnail_url'];

    /**
     * Get the full URL for the image.
     *
     * @return string|null
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }

        if (Str::startsWith($this->image_path, ['http://', 'https://'])) {
            return $this->image_path;
        }

        return Storage::disk('supabase')->url($this->image_path);
    }

    /**
     * Get the full URL for the thumbnail.
     *
     * @return string|null
     */
    public function getThumbnailUrlAttribute()
    {
        if (!$this->thumbnail_path) {
            // Fallback to main image if no thumbnail exists (backward compatibility)
            return $this->image_url;
        }

        if (Str::startsWith($this->thumbnail_path, ['http://', 'https://'])) {
            return $this->thumbnail_path;
        }

        return Storage::disk('supabase')->url($this->thumbnail_path);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::deleted(function ($image) {
            if ($image->image_path) {
                Storage::disk('supabase')->delete($image->image_path);
            }
            if ($image->thumbnail_path) {
                Storage::disk('supabase')->delete($image->thumbnail_path);
            }
        });
    }

    public function ad()
    {
        return $this->belongsTo(Ad::class);
    }
}
