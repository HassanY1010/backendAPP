<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavedSearchController extends Controller
{
    public function index(Request $request)
    {
        $savedSearches = $request->user()
            ->savedSearches()
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $savedSearches,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $filters = $this->normalizeFilters($validated['filters']);

        $savedSearch = $request->user()->savedSearches()->create([
            'name' => $validated['name'] ?? $this->buildDefaultName($filters),
            'filters' => $filters,
            'notify_enabled' => $validated['notify_enabled'] ?? true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Saved search created',
            'data' => $savedSearch,
        ], 201);
    }

    public function update(Request $request, SavedSearch $savedSearch)
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 403);

        $validated = $this->validatePayload($request, requiredFilters: false);
        $updates = [];

        if (array_key_exists('name', $validated)) {
            $updates['name'] = $validated['name'];
        }

        if (array_key_exists('filters', $validated)) {
            $updates['filters'] = $this->normalizeFilters($validated['filters']);
        }

        if (array_key_exists('notify_enabled', $validated)) {
            $updates['notify_enabled'] = $validated['notify_enabled'];
        }

        $savedSearch->update($updates);

        return response()->json([
            'status' => 'success',
            'message' => 'Saved search updated',
            'data' => $savedSearch->fresh(),
        ]);
    }

    public function destroy(Request $request, SavedSearch $savedSearch)
    {
        abort_unless($savedSearch->user_id === $request->user()->id, 403);

        $savedSearch->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Saved search deleted',
        ]);
    }

    private function validatePayload(Request $request, bool $requiredFilters = true): array
    {
        $filtersRule = $requiredFilters ? 'required|array' : 'sometimes|array';

        return $request->validate([
            'name' => 'sometimes|nullable|string|max:120',
            'notify_enabled' => 'sometimes|boolean',
            'filters' => $filtersRule,
            'filters.search' => 'sometimes|nullable|string|max:120',
            'filters.category_id' => 'sometimes|nullable|integer|exists:categories,id',
            'filters.location' => 'sometimes|nullable|string|max:120',
            'filters.city' => 'sometimes|nullable|string|max:120',
            'filters.min_price' => 'sometimes|nullable|numeric|min:0',
            'filters.max_price' => 'sometimes|nullable|numeric|min:0',
            'filters.currency' => 'sometimes|nullable|string|max:10',
            'filters.condition' => ['sometimes', 'nullable', Rule::in(['new', 'used', 'refurbished'])],
            'filters.status' => ['sometimes', 'nullable', Rule::in(['active', 'sold'])],
            'filters.sort' => ['sometimes', 'nullable', Rule::in(['latest', 'cheapest', 'expensive', 'price_low', 'price_high', 'most_viewed', 'nearest'])],
            'filters.latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'filters.longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'filters.radius_km' => 'sometimes|nullable|numeric|min:1|max:500',
        ]);
    }

    private function normalizeFilters(array $filters): array
    {
        if (isset($filters['city']) && !isset($filters['location'])) {
            $filters['location'] = $filters['city'];
        }

        unset($filters['city']);

        foreach ($filters as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                unset($filters[$key]);
                continue;
            }

            $filters[$key] = $value;
        }

        if (
            isset($filters['min_price'], $filters['max_price'])
            && (float) $filters['min_price'] > (float) $filters['max_price']
        ) {
            [$filters['min_price'], $filters['max_price']] = [$filters['max_price'], $filters['min_price']];
        }

        return $filters;
    }

    private function buildDefaultName(array $filters): string
    {
        if (!empty($filters['search'])) {
            return 'بحث عن ' . $filters['search'];
        }

        if (!empty($filters['location'])) {
            return 'بحث في ' . $filters['location'];
        }

        return 'بحث محفوظ';
    }
}
