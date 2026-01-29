<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'title',
        'slug',
        'description',
        'icon',
        'image',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::deleting(function ($category) {
            // Delete associated ads
            $category->ads()->each(function ($ad) {
                $ad->delete();
            });

            // Delete children categories
            $category->children()->each(function ($child) {
                $child->delete();
            });

            // Delete physical image/icon if they exist
            if ($category->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($category->image);
            }
        });
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function fields()
    {
        return $this->hasMany(CategoryField::class)->orderBy('sort_order');
    }

    public function ads()
    {
        return $this->hasMany(Ad::class);
    }
}
