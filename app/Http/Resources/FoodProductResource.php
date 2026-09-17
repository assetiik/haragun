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
            'price' => $this->price,
            'price_label' => $this->priceLabel(),
            'meta' => $this->meta,
            'subtitle' => $this->foodSubtitle(),
            'food_section' => $this->food_section,
            'icon_key' => $this->icon_key,
            'selection_mode' => 'quantity',
        ];
    }
}
