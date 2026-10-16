<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Enums\MenuCatalog;
use App\Filament\Concerns\FillsVenueTypeFromRequest;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use App\Filament\Support\VenueSection;
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

        if (VenueSection::isFoodCategoryContext(tab: $this->venueTab)) {
            $data['catalog'] = MenuCatalog::Drinks->value;
            $data['establishment_id'] = null;
            $data['requires_booking'] = false;
            $data['slug'] = null;
        }

        return $data;
    }
}
