<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Filament\Concerns\HasHaragunAndCafeTabs;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use Filament\Resources\Pages\ListRecords;

class ListMenuCategories extends ListRecords
{
    use HasHaragunAndCafeTabs;

    protected static string $resource = MenuCategoryResource::class;

    protected function haragunTitle(): string
    {
        return 'Категории';
    }

    protected function cafeTitle(): string
    {
        return 'Категории';
    }
}
