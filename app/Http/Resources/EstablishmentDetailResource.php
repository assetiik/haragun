<?php

namespace App\Http\Resources;

use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Establishment
 */
class EstablishmentDetailResource extends JsonResource
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
            'header_color' => $this->header_color,
            'image_url' => $this->publicImageUrl(),
            'images' => $this->carouselImages(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_open' => $this->isOpenAt(),
            'ice_temperature' => $this->ice_temperature,
            'ice_note' => $this->ice_note,
            'parking_is_free' => $this->parking_is_free,
            'session_duration_minutes' => $this->session_duration_minutes,
            'opens_at' => $this->formattedTime($this->opens_at),
            'closes_at' => $this->formattedTime($this->closes_at),
            'distance_km' => $this->distanceKm($latitude, $longitude),
            'is_favorite' => $this->isFavoritedBy($request->user('sanctum')),
        ];
    }
}
