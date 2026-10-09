<?php

namespace App\Filament\Resources\PromoTiles\Pages;

use App\Filament\Resources\PromoTiles\PromoTileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromoTiles extends ListRecords
{
    protected static string $resource = PromoTileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
