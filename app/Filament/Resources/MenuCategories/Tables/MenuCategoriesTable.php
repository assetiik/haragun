<?php

namespace App\Filament\Resources\MenuCategories\Tables;

use App\Enums\MenuCatalog;
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
                    ->hidden(fn (): bool => in_array(VenueSection::fromRequest(), [
                        VenueSection::Cafes,
                        VenueSection::HaragunFood,
                    ], true)),
                TextColumn::make('catalog')
                    ->label('Раздел')
                    ->badge()
                    ->formatStateUsing(fn (?MenuCatalog $state): string => $state?->label() ?? '—')
                    ->visible(fn (): bool => VenueSection::fromRequest() === VenueSection::HaragunFood),
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
                    ->hidden(fn (): bool => in_array(VenueSection::fromRequest(), [
                        VenueSection::Cafes,
                        VenueSection::HaragunFood,
                    ], true)),
                TextColumn::make('prep_minutes')
                    ->label('Мин.')
                    ->sortable()
                    ->hidden(fn (): bool => in_array(VenueSection::fromRequest(), [
                        VenueSection::Cafes,
                        VenueSection::HaragunFood,
                    ], true)),
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
