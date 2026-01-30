<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'location' => $this->location,
            'is_negotiable' => $this->is_negotiable,
            'condition' => $this->condition,
            'status' => $this->status,
            'views' => $this->views,
            'contact_phone' => $this->contact_phone,
            'contact_whatsapp' => $this->contact_whatsapp,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->title, // Flutter expects 'name' field
                    'title' => $this->category->title, // Keep for backward compatibility
                ];
            }),
            'category_id' => $this->category_id, // Include category_id for fallback logic
            'main_image' => $this->whenLoaded('mainImage', function () {
                return [
                    'image_url' => $this->mainImage->image_url, // Uses the accessor with local-cdn
                    'image_path' => $this->mainImage->image_path,
                ];
            }),
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($img) {
                    return [
                        'image_url' => $img->image_url, // Uses the accessor with local-cdn
                        'image_path' => $img->image_path,
                    ];
                });
            }),
            'is_liked' => (bool) ($this->is_liked ?? false),
            'likes_count' => (int) ($this->likes_count ?? 0),
            'custom_fields' => $this->whenLoaded('customFields', function () {
                return $this->customFields->map(function ($field) {
                    return [
                        'name' => $field->field->name ?? '',
                        'label' => $field->field->label ?? '',
                        'value' => $field->value,
                    ];
                });
            }),
        ];
    }
}
