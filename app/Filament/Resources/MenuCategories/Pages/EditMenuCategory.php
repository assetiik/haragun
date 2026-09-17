<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Filament\Concerns\RedirectsToVenueIndex;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
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
}
