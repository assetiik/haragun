<?php

namespace App\Filament\Resources\LocalizationStrings\Pages;

use App\Filament\Resources\LocalizationStrings\LocalizationStringResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLocalizationString extends EditRecord
{
    protected static string $resource = LocalizationStringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
