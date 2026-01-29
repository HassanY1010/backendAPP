<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership check is done in Controller usually, or here if we load route param
        // For simplicity, we authorize generic access and rely on controller's ownership query
        return true; 
    }

    public function rules(): array
    {
        return [
            'title' => 'string|max:255',
            'description' => 'string',
            'price' => 'numeric',
            'location' => 'string',
            'is_negotiable' => 'boolean',
            'condition' => 'nullable|string|in:new,used,refurbished',
            'category_id' => 'exists:categories,id',
        ];
    }
}
