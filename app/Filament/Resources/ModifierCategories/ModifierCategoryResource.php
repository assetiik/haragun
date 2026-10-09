<?php

namespace App\Filament\Resources\ModifierCategories;

use App\Filament\Resources\ModifierCategories\Pages\CreateModifierCategory;
use App\Filament\Resources\ModifierCategories\Pages\EditModifierCategory;
use App\Filament\Resources\ModifierCategories\Pages\ListModifierCategories;
use App\Filament\Resources\ModifierCategories\Schemas\ModifierCategoryForm;
use App\Filament\Resources\ModifierCategories\Tables\ModifierCategoriesTable;
use App\Models\ModifierCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ModifierCategoryResource extends Resource
{
    protected static ?string $model = ModifierCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'Кофейни';

    protected static ?string $navigationLabel = 'Категории добавок';

    protected static ?string $modelLabel = 'категорию добавок';

    protected static ?string $pluralModelLabel = 'Категории добавок';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ModifierCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModifierCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModifierCategories::route('/'),
            'create' => CreateModifierCategory::route('/create'),
            'edit' => EditModifierCategory::route('/{record}/edit'),
        ];
    }
}
