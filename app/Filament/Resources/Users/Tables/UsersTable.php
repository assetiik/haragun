<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->formatStateUsing(fn ($state, User $record): string => $record->formattedPhone()),
                TextColumn::make('name')
                    ->label('Имя')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('guest_number')
                    ->label('Номер гостя')
                    ->state(fn (User $record): string => 'Гость '.$record->guestNumber()),
                TextColumn::make('created_at')
                    ->label('Регистрация')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()->label('Изменить'),
                DeleteAction::make()->label('Удалить'),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
