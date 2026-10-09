<?php

namespace App\Http\Resources;

use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Establishment
 */
class BrandAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latitude = $request->attributes->get('user_latitude');
        $longitude = $request->attributes->get('user_longitude');
        $selectedId = $request->attributes->get('selected_address_id');

        return [
            'id' => $this->id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_open' => $this->isOpenAt(),
            'opens_at' => $this->formattedTime($this->opens_at),
            'closes_at' => $this->formattedTime($this->closes_at),
            'distance_km' => $this->distanceKm($latitude, $longitude),
            'is_selected' => $selectedId !== null && (int) $selectedId === $this->id,
        ];
    }
}
