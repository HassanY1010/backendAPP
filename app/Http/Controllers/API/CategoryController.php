<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = \Illuminate\Support\Facades\Cache::rememberForever('category_tree', function () {
            return Category::whereNull('parent_id')
                ->with(['children.children.children.children', 'fields']) // Deep load for up to 5 levels to be safe
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(function ($category) {
                    return $this->transformCategory($category);
                });
        });

        return response()->json([
            'data' => $categories
        ]);
    }

    private function transformCategory($category)
    {
        $data = $category->toArray();
        $data['name'] = $category->title; // Add 'name' field for Flutter compatibility
        
        // Transform children recursively
        if (isset($data['children']) && is_array($data['children'])) {
            $data['children'] = array_map(function ($child) {
                $child['name'] = $child['title'] ?? null;
                
                // Transform nested children
                if (isset($child['children']) && is_array($child['children'])) {
                    $child['children'] = array_map(function ($grandchild) {
                        $grandchild['name'] = $grandchild['title'] ?? null;
                        
                        // Transform great-grandchildren
                        if (isset($grandchild['children']) && is_array($grandchild['children'])) {
                            $grandchild['children'] = array_map(function ($greatGrandchild) {
                                $greatGrandchild['name'] = $greatGrandchild['title'] ?? null;
                                return $greatGrandchild;
                            }, $grandchild['children']);
                        }
                        
                        return $grandchild;
                    }, $child['children']);
                }
                
                return $child;
            }, $data['children']);
        }
        
        return $data;
    }
}
