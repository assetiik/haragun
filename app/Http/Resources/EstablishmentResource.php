<?php

namespace App\Http\Resources;

use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Establishment
 */
class EstablishmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latitude = $request->attributes->get('user_latitude');
        $longitude = $request->attributes->get('user_longitude');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'type' => $this->type->value,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_open' => $this->isOpenAt(),
            'opens_at' => substr((string) $this->opens_at, 0, 5),
            'closes_at' => substr((string) $this->closes_at, 0, 5),
            'distance_km' => $this->distanceKm($latitude, $longitude),
        ];
    }
}
