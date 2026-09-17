<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Filament\Concerns\FillsVenueTypeFromRequest;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuCategory extends CreateRecord
{
    use FillsVenueTypeFromRequest;

    protected static string $resource = MenuCategoryResource::class;
}
