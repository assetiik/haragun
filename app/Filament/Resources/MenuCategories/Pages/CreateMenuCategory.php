<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Filament\Concerns\FillsVenueTypeFromRequest;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuCategory extends CreateRecord
{
    use FillsVenueTypeFromRequest {
        mutateFormDataBeforeCreate as fillVenueTypeBeforeCreate;
    }

    protected static string $resource = MenuCategoryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->fillVenueTypeBeforeCreate($data);

        if (filled($data['catalog'] ?? null)) {
            $data['establishment_id'] = null;
            $data['requires_booking'] = false;
            $data['slug'] = null;
        }

        return $data;
    }
}
