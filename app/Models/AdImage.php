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
        return $this->image_path ? url('local-cdn/' . $this->image_path) : null;
    }

    /**
     * Get the full URL for the thumbnail.
     *
     * @return string|null
     */
    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail_path ? url('local-cdn/' . $this->thumbnail_path) : null;
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::deleted(function ($image) {
            if ($image->image_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($image->image_path);
            }
            if ($image->thumbnail_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($image->thumbnail_path);
            }
        });
    }

    public function ad()
    {
        return $this->belongsTo(Ad::class);
    }
}
