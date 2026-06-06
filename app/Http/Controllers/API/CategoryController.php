<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    private const CACHE_KEY = 'category_tree:v2';
    private const LEGACY_CACHE_KEY = 'category_tree';

    public function index()
    {
        $categories = Cache::get(self::CACHE_KEY);

        if (!$this->hasCategories($categories)) {
            Cache::forget(self::LEGACY_CACHE_KEY);
            Cache::forget(self::CACHE_KEY);

            $categories = $this->buildCategoryTree();

            if ($this->hasCategories($categories)) {
                Cache::put(self::CACHE_KEY, $categories, now()->addHours(12));
            }
        }

        return response()->json([
            'data' => $categories instanceof Collection ? $categories->values() : $categories,
        ]);
    }

    private function buildCategoryTree(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with($this->categoryRelations())
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (Category $category) => $this->transformCategory($category));
    }

    private function categoryRelations(int $maxDepth = 5): array
    {
        $relations = [
            'fields' => fn ($query) => $query->orderBy('sort_order'),
        ];

        $path = '';
        for ($depth = 0; $depth < $maxDepth; $depth++) {
            $path = $path === '' ? 'children' : "{$path}.children";
            $relations[$path] = fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('title');
            $relations["{$path}.fields"] = fn ($query) => $query->orderBy('sort_order');
        }

        return $relations;
    }

    private function transformCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'title' => $category->title,
            'name' => $category->title,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon' => $category->icon,
            'image' => $category->image,
            'color' => $category->color,
            'is_active' => (bool) $category->is_active,
            'sort_order' => $category->sort_order,
            'fields' => $category->relationLoaded('fields') ? $category->fields->values() : [],
            'children' => $category->relationLoaded('children')
                ? $category->children
                    ->filter(fn (Category $child) => $child->is_active)
                    ->sortBy([
                        ['sort_order', 'asc'],
                        ['title', 'asc'],
                    ])
                    ->values()
                    ->map(fn (Category $child) => $this->transformCategory($child))
                : [],
        ];
    }

    private function hasCategories(mixed $categories): bool
    {
        if ($categories instanceof Collection) {
            return $categories->isNotEmpty();
        }

        return is_array($categories) && count($categories) > 0;
    }
}
