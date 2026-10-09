<?php

namespace App\Http\Resources;

use App\Models\PromoTile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromoTile
 */
class PromoTileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'promo',
            'title' => $this->title,
            'description' => $this->title,
            'image_url' => $this->publicImageUrl(),
        ];
    }
}
