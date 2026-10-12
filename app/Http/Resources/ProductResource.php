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
        $bookingDate = $request->attributes->get('booking_date');

        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'name' => $this->name,
            'price' => $this->priceOn(is_string($bookingDate) ? $bookingDate : null),
            'meta' => $this->meta,
            'age_from' => $this->age_from,
        ];
    }
}
