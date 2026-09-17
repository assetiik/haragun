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
            'type_label' => $this->type->label(),
            'header_color' => $this->header_color,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_open' => $this->isOpenAt(),
            'status_label' => $this->statusLabel(),
            'ice_temperature' => $this->ice_temperature,
            'ice_note' => $this->ice_note,
            'ice_status_label' => $this->iceStatusLabel(),
            'parking_is_free' => $this->parking_is_free,
            'session_duration_minutes' => $this->session_duration_minutes,
            'opens_at' => $this->formattedTime($this->opens_at),
            'closes_at' => $this->formattedTime($this->closes_at),
            'distance_km' => $this->distanceKm($latitude, $longitude),
            'distance_label' => $this->distanceLabel($latitude, $longitude),
            'location_meta' => $this->locationMeta($latitude, $longitude),
            'is_favorite' => false,
        ];
    }
}
