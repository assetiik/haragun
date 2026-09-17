<?php

namespace App\Filament\Resources\Establishments\Pages;

use App\Filament\Concerns\HasHaragunAndCafeTabs;
use App\Filament\Resources\Establishments\EstablishmentResource;
use Filament\Resources\Pages\ListRecords;

class ListEstablishments extends ListRecords
{
    use HasHaragunAndCafeTabs;

    protected static string $resource = EstablishmentResource::class;

    protected function haragunTitle(): string
    {
        return 'Арена';
    }

    protected function cafeTitle(): string
    {
        return 'Точки';
    }
}
