<?php

namespace App\Filament\Resources\PromoTiles\Pages;

use App\Filament\Resources\PromoTiles\PromoTileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromoTile extends EditRecord
{
    protected static string $resource = PromoTileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
