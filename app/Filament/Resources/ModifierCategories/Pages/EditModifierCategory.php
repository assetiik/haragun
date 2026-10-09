<?php

namespace App\Filament\Resources\ModifierCategories\Pages;

use App\Filament\Resources\ModifierCategories\ModifierCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModifierCategory extends EditRecord
{
    protected static string $resource = ModifierCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
