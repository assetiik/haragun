<?php

namespace App\Filament\Resources\MenuCategories\Tables;

use App\Filament\Support\VenueSection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenuCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('establishment.name')
                    ->label('Заведение')
                    ->searchable()
                    ->hidden(fn (): bool => VenueSection::fromRequest() === VenueSection::Cafes),
                TextColumn::make('slug')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'tea' => 'Чай',
                        'coffee' => 'Кофе',
                        'lemonade' => 'Лимонад',
                        'foodcourt' => 'Фудкорт',
                        'snack' => 'Перекус',
                        'ice_rental' => 'Лёд',
                        'menu' => 'Меню',
                        default => $state,
                    })
                    ->hidden(fn (): bool => VenueSection::fromRequest() === VenueSection::Cafes),
                TextColumn::make('prep_minutes')
                    ->label('Мин.')
                    ->sortable()
                    ->hidden(fn (): bool => VenueSection::fromRequest() === VenueSection::Cafes),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()->label('Изменить'),
                DeleteAction::make()->label('Удалить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Удалить'),
                ]),
            ]);
    }
}
