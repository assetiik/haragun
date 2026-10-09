<?php

namespace App\Filament\Resources\PromoTiles;

use App\Filament\Resources\PromoTiles\Pages\CreatePromoTile;
use App\Filament\Resources\PromoTiles\Pages\EditPromoTile;
use App\Filament\Resources\PromoTiles\Pages\ListPromoTiles;
use App\Filament\Resources\PromoTiles\Schemas\PromoTileForm;
use App\Filament\Resources\PromoTiles\Tables\PromoTilesTable;
use App\Models\PromoTile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PromoTileResource extends Resource
{
    protected static ?string $model = PromoTile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    protected static ?string $navigationLabel = 'Промо-плитки';

    protected static ?string $modelLabel = 'промо-плитку';

    protected static ?string $pluralModelLabel = 'Промо-плитки';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PromoTileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromoTilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoTiles::route('/'),
            'create' => CreatePromoTile::route('/create'),
            'edit' => EditPromoTile::route('/{record}/edit'),
        ];
    }
}
