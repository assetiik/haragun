<?php

namespace App\Filament\Resources\Establishments\Tables;

use App\Enums\EstablishmentType;
use App\Filament\Support\VenueSection;
use App\Models\Establishment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EstablishmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Фото')
                    ->getStateUsing(fn (Establishment $record): ?string => $record->publicImageUrl())
                    ->circular()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city.name')
                    ->label('Город')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label('Адрес')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('latitude')
                    ->label('Lat')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('longitude')
                    ->label('Lon')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (EstablishmentType $state): string => $state->label())
                    ->hidden(fn (): bool => VenueSection::fromRequest() !== null),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Активно')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Сорт.')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
