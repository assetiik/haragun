<?php

namespace App\Http\Resources;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Brand
 */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latitude = $request->attributes->get('user_latitude');
        $longitude = $request->attributes->get('user_longitude');
        $nearest = $this->nearestEstablishment($latitude, $longitude);
        $addressesCount = $this->establishments->where('is_active', true)->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'icon_key' => $this->icon_key,
            'accent_color' => $this->accent_color,
            'addresses_count' => $addressesCount,
            'requires_address_selection' => $addressesCount > 1,
            'default_establishment_id' => $nearest?->id,
            'nearest_address' => $nearest ? [
                'id' => $nearest->id,
                'address' => $nearest->address,
                'is_open' => $nearest->isOpenAt(),
                'status_label' => $nearest->statusLabel(),
                'distance_km' => $nearest->distanceKm($latitude, $longitude),
                'distance_label' => $nearest->distanceLabel($latitude, $longitude),
            ] : null,
        ];
    }
}
