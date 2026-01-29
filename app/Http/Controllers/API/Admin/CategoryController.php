<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('children')
            ->whereNull('parent_id')
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
        ]);

        $category = Category::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title) . '-' . time(),
            'parent_id' => $request->parent_id,
            'icon' => $request->icon,
            'is_active' => true,
        ]);

        return response()->json(['message' => 'Category created', 'data' => $category], 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $request->validate([
            'title' => 'string|max:255',
            'parent_id' => 'nullable|exists:categories,id',
            'icon' => 'nullable|string',
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
             $category->slug = Str::slug($request->title) . '-' . $category->id;
        }
        
        if ($request->has('icon')) $category->icon = $request->icon;
        if ($request->has('is_active')) $category->is_active = $request->is_active;
        
        $category->save();

        return response()->json(['message' => 'Category updated', 'data' => $category]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }
}
