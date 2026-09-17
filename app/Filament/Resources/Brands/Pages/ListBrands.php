<?php

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Concerns\HasHaragunAndCafeTabs;
use App\Filament\Resources\Brands\BrandResource;
use Filament\Resources\Pages\ListRecords;

class ListBrands extends ListRecords
{
    use HasHaragunAndCafeTabs;

    protected static string $resource = BrandResource::class;

    protected function haragunTitle(): string
    {
        return 'Бренд';
    }

    protected function cafeTitle(): string
    {
        return 'Бренды';
    }
}
