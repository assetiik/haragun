<?php

namespace App\Http\Resources;

use App\Models\MenuCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MenuCategory
 */
class MenuCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'requires_booking' => $this->requires_booking,
            'section_title' => $this->section_title,
            'section_subtitle' => $this->section_subtitle,
            'is_foodcourt' => $this->isFoodCourt(),
        ];
    }
}
