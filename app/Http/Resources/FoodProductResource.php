<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class FoodProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'meta' => $this->meta,
            'food_section' => $this->food_section,
            'icon_key' => $this->icon_key,
            'image_url' => $this->publicImageUrl(),
            'selection_mode' => 'quantity',
            'sizes' => [],
            'modifiers' => [],
            'modifier_groups' => [],
        ];
    }
}
