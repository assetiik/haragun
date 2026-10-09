<?php

namespace App\Filament\Resources\Products\Tables;

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
            ->modifyQueryUsing(fn ($query) => $query->with(['establishment', 'brand']))
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
                TextColumn::make('brand.name')
                    ->label('Бренд')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('establishment.name')
                    ->label('Точка')
                    ->description(fn (Product $record): ?string => $record->establishment?->address)
                    ->placeholder('Все точки бренда')
                    ->searchable(query: function ($query, string $search): void {
                        $query->where(function ($inner) use ($search): void {
                            $inner->whereHas(
                                'establishment',
                                fn ($establishmentQuery) => $establishmentQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('address', 'like', "%{$search}%"),
                            )->orWhereHas(
                                'brand',
                                fn ($brandQuery) => $brandQuery->where('name', 'like', "%{$search}%"),
                            );
                        });
                    })
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('menuCategory.name')
                    ->label('Категория')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('price')
                    ->label('Цена')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
