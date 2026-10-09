<?php

namespace App\Filament\Resources\LocalizationStrings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocalizationStringsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->columns([
                TextColumn::make('section')
                    ->label('Раздел')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('key')
                    ->label('Ключ')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('ru')
                    ->label('Русский')
                    ->searchable()
                    ->wrap()
                    ->limit(80),
                TextColumn::make('kz')
                    ->label('Қазақша')
                    ->searchable()
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),
                TextColumn::make('en')
                    ->label('English')
                    ->searchable()
                    ->wrap()
                    ->limit(80)
                    ->toggleable(),
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
