<?php

namespace App\Http\Resources;

use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Banner
 */
class BannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'header',
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'button_label' => $this->button_label,
            'background_color' => $this->background_color,
            'image_url' => $this->publicImageUrl(),
        ];
    }
}
