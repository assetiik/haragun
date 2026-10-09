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

        $imageUrl = $this->publicImageUrl() ?? $nearest?->publicImageUrl();
        $images = $this->carouselImages();

        if ($images === [] && $nearest !== null) {
            $images = $nearest->carouselImages();
        }

        if ($images === [] && $imageUrl !== null) {
            $images = [$imageUrl];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'subtitle' => $this->subtitle,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'icon_key' => $this->icon_key,
            'accent_color' => $this->accent_color,
            'image_url' => $imageUrl,
            'images' => $images,
            'addresses_count' => $addressesCount,
            'requires_address_selection' => $addressesCount > 1,
            'default_establishment_id' => $nearest?->id,
            'nearest_address' => $nearest ? [
                'id' => $nearest->id,
                'address' => $nearest->address,
                'is_open' => $nearest->isOpenAt(),
                'opens_at' => $nearest->formattedTime($nearest->opens_at),
                'closes_at' => $nearest->formattedTime($nearest->closes_at),
                'distance_km' => $nearest->distanceKm($latitude, $longitude),
                'image_url' => $nearest->publicImageUrl() ?? $imageUrl,
            ] : null,
        ];
    }
}
