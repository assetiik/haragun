<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'age_from' => $this->age_from,
            'subtitle' => collect([$this->priceLabel(), $this->meta])
                ->filter()
                ->implode(' · '),
        ];
    }
}
