<?php

namespace App\Filament\Resources\LocalizationStrings;

use App\Filament\Resources\LocalizationStrings\Pages\CreateLocalizationString;
use App\Filament\Resources\LocalizationStrings\Pages\EditLocalizationString;
use App\Filament\Resources\LocalizationStrings\Pages\ListLocalizationStrings;
use App\Filament\Resources\LocalizationStrings\Schemas\LocalizationStringForm;
use App\Filament\Resources\LocalizationStrings\Tables\LocalizationStringsTable;
use App\Models\LocalizationString;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LocalizationStringResource extends Resource
{
    protected static ?string $model = LocalizationString::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|UnitEnum|null $navigationGroup = 'Локализация';

    protected static ?string $navigationLabel = 'Слова';

    protected static ?string $modelLabel = 'слово';

    protected static ?string $pluralModelLabel = 'Слова';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return LocalizationStringForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LocalizationStringsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocalizationStrings::route('/'),
            'create' => CreateLocalizationString::route('/create'),
            'edit' => EditLocalizationString::route('/{record}/edit'),
        ];
    }
}
