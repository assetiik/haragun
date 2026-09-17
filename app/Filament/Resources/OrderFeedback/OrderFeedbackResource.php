<?php

namespace App\Filament\Resources\OrderFeedback;

use App\Filament\Resources\OrderFeedback\Pages\CreateOrderFeedback;
use App\Filament\Resources\OrderFeedback\Pages\EditOrderFeedback;
use App\Filament\Resources\OrderFeedback\Pages\ListOrderFeedback;
use App\Filament\Resources\OrderFeedback\Schemas\OrderFeedbackForm;
use App\Filament\Resources\OrderFeedback\Tables\OrderFeedbackTable;
use App\Models\OrderFeedback;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OrderFeedbackResource extends Resource
{
    protected static ?string $model = OrderFeedback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Заказы';

    protected static ?string $navigationLabel = 'Отзывы';

    protected static ?string $modelLabel = 'отзыв';

    protected static ?string $pluralModelLabel = 'Отзывы';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return OrderFeedbackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrderFeedbackTable::configure($table);
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
            'index' => ListOrderFeedback::route('/'),
            'create' => CreateOrderFeedback::route('/create'),
            'edit' => EditOrderFeedback::route('/{record}/edit'),
        ];
    }
}
