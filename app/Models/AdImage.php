<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        if (!$this->image_path)
            return null;

        // Manually construct Supabase public URL
        // Format: https://{project}.supabase.co/storage/v1/object/public/{bucket}/{path}
        $supabaseUrl = env('SUPABASE_URL');
        $bucket = 'uploads';
        return "{$supabaseUrl}/storage/v1/object/public/{$bucket}/{$this->image_path}";
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

        // Manually construct Supabase public URL for thumbnail
        $supabaseUrl = env('SUPABASE_URL');
        $bucket = 'uploads';
        return "{$supabaseUrl}/storage/v1/object/public/{$bucket}/{$this->thumbnail_path}";
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::deleted(function ($image) {
            if ($image->image_path) {
                \Illuminate\Support\Facades\Storage::disk('supabase')->delete($image->image_path);
            }
            if ($image->thumbnail_path) {
                \Illuminate\Support\Facades\Storage::disk('supabase')->delete($image->thumbnail_path);
            }
        });
    }

    public function ad()
    {
        return $this->belongsTo(Ad::class);
    }
}
