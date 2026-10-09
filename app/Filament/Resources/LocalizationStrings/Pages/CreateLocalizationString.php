<?php

namespace App\Filament\Resources\LocalizationStrings\Pages;

use App\Filament\Resources\LocalizationStrings\LocalizationStringResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLocalizationString extends CreateRecord
{
    protected static string $resource = LocalizationStringResource::class;
}
