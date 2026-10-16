<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Enums\MenuCatalog;
use App\Filament\Concerns\RedirectsToVenueIndex;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use App\Filament\Support\VenueSection;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMenuCategory extends EditRecord
{
    use RedirectsToVenueIndex;

    protected static string $resource = MenuCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (VenueSection::isFoodCategoryContext($this->getRecord())) {
            $data['catalog'] = MenuCatalog::Drinks->value;
            $data['establishment_id'] = null;
            $data['requires_booking'] = false;
        }

        return $data;
    }
}
