<?php

namespace App\Http\Resources;

use App\Enums\ProductKind;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class RentalProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sizes = $this->relationLoaded('skateSizes')
            ? $this->skateSizes->map(fn ($size) => [
                'size' => $size->size,
                'is_available' => $size->is_available,
            ])->values()->all()
            : [];

        $bookingDate = $request->attributes->get('booking_date');

        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'name' => $this->name,
            'price' => $this->priceOn(is_string($bookingDate) ? $bookingDate : null),
            'meta' => $this->meta,
            'requires_size' => $this->requires_size,
            'size_min' => $this->size_min,
            'size_max' => $this->size_max,
            'selection_mode' => $this->kind === ProductKind::RentalSkates ? 'sizes' : 'quantity',
            'sizes' => $sizes,
        ];
    }
}
