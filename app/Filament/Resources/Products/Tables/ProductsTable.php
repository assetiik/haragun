<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Support\VenueSection;
use App\Models\Product;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('establishment'))
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_url')
                    ->label('')
                    ->circular()
                    ->getStateUsing(fn (Product $record): ?string => $record->publicImageUrl())
                    ->defaultImageUrl('https://placehold.co/80x80?text=☕'),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('establishment.name')
                    ->label('Точка')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('menuCategory.name')
                    ->label('Категория')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                IconColumn::make('is_popular')
                    ->label('Популярн.')
                    ->boolean()
                    ->visible(fn (): bool => VenueSection::isCafeContext()),
                IconColumn::make('is_featured')
                    ->label('Баннер')
                    ->boolean()
                    ->visible(fn (): bool => VenueSection::isCafeContext()),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_popular')
                    ->label('Популярные')
                    ->visible(fn (): bool => VenueSection::isCafeContext()),
                TernaryFilter::make('is_featured')
                    ->label('В баннере')
                    ->visible(fn (): bool => VenueSection::isCafeContext()),
                TernaryFilter::make('is_active')->label('Активные'),
            ])
            ->recordActions([
                EditAction::make()->label('Изменить'),
                DeleteAction::make()->label('Удалить'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
