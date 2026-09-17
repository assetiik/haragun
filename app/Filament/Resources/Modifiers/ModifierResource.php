<?php

namespace App\Filament\Resources\Modifiers;

use App\Filament\Resources\Modifiers\Pages\CreateModifier;
use App\Filament\Resources\Modifiers\Pages\EditModifier;
use App\Filament\Resources\Modifiers\Pages\ListModifiers;
use App\Filament\Resources\Modifiers\Schemas\ModifierForm;
use App\Filament\Resources\Modifiers\Tables\ModifiersTable;
use App\Models\Modifier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ModifierResource extends Resource
{
    protected static ?string $model = Modifier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'Кофейни';

    protected static ?string $navigationLabel = 'Добавки';

    protected static ?string $modelLabel = 'добавка';

    protected static ?string $pluralModelLabel = 'Добавки';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ModifierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModifiersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModifiers::route('/'),
            'create' => CreateModifier::route('/create'),
            'edit' => EditModifier::route('/{record}/edit'),
        ];
    }
}
