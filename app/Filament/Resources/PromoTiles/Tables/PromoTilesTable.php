<?php

namespace App\Filament\Resources\PromoTiles\Tables;

use App\Models\PromoTile;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromoTilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Фото')
                    ->getStateUsing(fn (PromoTile $record): ?string => $record->publicImageUrl()),
                TextColumn::make('title')
                    ->label('Описание')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('city.name')
                    ->label('Город')
                    ->placeholder('Все')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активна')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->label('Сорт.')
                    ->sortable(),
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
