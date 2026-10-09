<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class CoffeeProductCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'image_url' => $this->publicImageUrl(),
            'background_color' => $this->background_color,
            'is_popular' => $this->is_popular,
            'is_featured' => $this->is_featured,
            'category_slug' => $this->menuCategory?->slug,
        ];
    }
}
