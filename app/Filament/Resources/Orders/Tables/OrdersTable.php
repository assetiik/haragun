<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('establishment'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('number')
                    ->label('Номер')
                    ->sortable(),
                TextColumn::make('guest_number')
                    ->label('Гость')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "Гость {$state}" : '—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Accepted => 'Принят',
                        OrderStatus::Preparing => 'Готовится',
                        OrderStatus::Ready => 'Готов',
                        OrderStatus::Confirmed => 'Подтверждён',
                        OrderStatus::Completed => 'Завершён',
                        OrderStatus::Cancelled => 'Отменён',
                    })
                    ->color(fn (OrderStatus $state): string => match ($state) {
                        OrderStatus::Accepted, OrderStatus::Confirmed => 'info',
                        OrderStatus::Preparing => 'warning',
                        OrderStatus::Ready => 'success',
                        OrderStatus::Completed => 'gray',
                        OrderStatus::Cancelled => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('establishment.name')
                    ->label('Заведение')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Сумма')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (OrderType $state): string => match ($state) {
                        OrderType::Venue => 'В заведении',
                        OrderType::Tickets => 'Билеты',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(collect(OrderStatus::cases())->mapWithKeys(
                        fn (OrderStatus $status): array => [$status->value => match ($status) {
                            OrderStatus::Accepted => 'Принят',
                            OrderStatus::Preparing => 'Готовится',
                            OrderStatus::Ready => 'Готов',
                            OrderStatus::Confirmed => 'Подтверждён',
                            OrderStatus::Completed => 'Завершён',
                            OrderStatus::Cancelled => 'Отменён',
                        }]
                    )->all()),
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        OrderType::Venue->value => 'В заведении',
                        OrderType::Tickets->value => 'Билеты',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Открыть'),
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
