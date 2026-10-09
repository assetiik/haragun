<?php

namespace App\Filament\Resources\Brands\Tables;

use App\Enums\EstablishmentType;
use App\Filament\Support\VenueSection;
use App\Models\Brand;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BrandsTable
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
                ImageColumn::make('image_url')
                    ->label('Фото')
                    ->getStateUsing(fn (Brand $record): ?string => $record->publicImageUrl())
                    ->circular()
                    ->toggleable(),
                TextColumn::make('city.name')
                    ->label('Город')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (EstablishmentType $state): string => $state->label())
                    ->hidden(fn (): bool => VenueSection::fromRequest() !== null),
                IconColumn::make('is_active')
                    ->label('Активен')
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
