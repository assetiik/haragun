<?php

namespace App\Filament\Resources\ModifierCategories\Pages;

use App\Filament\Resources\ModifierCategories\ModifierCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModifierCategories extends ListRecords
{
    protected static string $resource = ModifierCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
