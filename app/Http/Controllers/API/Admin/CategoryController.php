<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with($this->childrenRelations())
            ->whereNull('parent_id')
            ->orderBy('sort_order', 'asc')
            ->orderBy('title')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'icon' => 'nullable|string',
            'image' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $category = Category::create([
            'title' => $request->title,
            'slug' => $this->uniqueSlug($request->title),
            'parent_id' => $request->parent_id,
            'icon' => $request->icon,
            'image' => $request->image,
            'color' => $request->color ?: '#64748B',
            'sort_order' => $request->integer('sort_order', 0),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        $this->forgetCategoryCache();

        return response()->json(['message' => 'Category created', 'data' => $category], 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'title' => 'string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'icon' => 'nullable|string',
            'image' => 'nullable|string|max:1000',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        if ($request->has('parent_id')) {
            if ($request->parent_id == $id) {
                return response()->json(['message' => 'Category cannot be its own parent'], 422);
            }
            // Optional: You could add a recursive check here to ensure no cycles
            $category->parent_id = $request->parent_id;
        }

        if ($request->has('title')) {
            $category->title = $request->title;
            $category->slug = $this->uniqueSlug($request->title, $category->id);
        }

        if ($request->has('icon'))
            $category->icon = $request->icon;
        if ($request->has('image'))
            $category->image = $request->image;
        if ($request->has('color'))
            $category->color = $request->color ?: '#64748B';
        if ($request->has('sort_order'))
            $category->sort_order = $request->integer('sort_order');
        if ($request->has('is_active'))
            $category->is_active = $request->is_active;

        $category->save();

        $this->forgetCategoryCache();

        return response()->json(['message' => 'Category updated', 'data' => $category]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        $this->forgetCategoryCache();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    private function childrenRelations(int $maxDepth = 5): array
    {
        $relations = [];
        $path = '';

        for ($depth = 0; $depth < $maxDepth; $depth++) {
            $path = $path === '' ? 'children' : "{$path}.children";
            $relations[$path] = fn ($query) => $query
                ->orderBy('sort_order', 'asc')
                ->orderBy('title');
        }

        return $relations;
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'category';
        }

        $slug = $base;
        $counter = 1;

        while (
            Category::where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function forgetCategoryCache(): void
    {
        Cache::forget('category_tree:v2');
        Cache::forget('category_tree');
    }
}
