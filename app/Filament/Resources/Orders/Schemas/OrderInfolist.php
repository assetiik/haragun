<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Support\Money;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Клиент и точка')
                    ->schema([
                        TextEntry::make('guest_number')
                            ->label('Гость')
                            ->formatStateUsing(fn (?int $state): string => $state ? "Гость {$state}" : '—'),
                        TextEntry::make('user.name')
                            ->label('Имя')
                            ->placeholder('Без имени'),
                        TextEntry::make('user.phone')
                            ->label('Телефон'),
                        TextEntry::make('establishment.name')
                            ->label('Заведение'),
                        TextEntry::make('establishment.address')
                            ->label('Адрес'),
                    ])
                    ->columns(2),
                Section::make('Заказ')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Код'),
                        TextEntry::make('number')
                            ->label('Номер выдачи'),
                        TextEntry::make('type')
                            ->label('Тип')
                            ->badge()
                            ->formatStateUsing(fn (OrderType $state): string => $state->tabTitle()),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->formatStateUsing(fn (OrderStatus $state): string => match ($state) {
                                OrderStatus::Accepted => 'Принят',
                                OrderStatus::Preparing => 'Готовится',
                                OrderStatus::Ready => 'Готов',
                                OrderStatus::Confirmed => 'Подтверждён',
                                OrderStatus::Completed => 'Выдан',
                                OrderStatus::Cancelled => 'Отменён',
                            }),
                        TextEntry::make('summary_title')
                            ->label('Состав'),
                        TextEntry::make('summary_short')
                            ->label('Кратко'),
                        TextEntry::make('total')
                            ->label('Сумма')
                            ->formatStateUsing(fn (int $state): string => Money::format($state)),
                        TextEntry::make('payment_label')
                            ->label('Оплата'),
                        TextEntry::make('prep_minutes')
                            ->label('Минут на готовку')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Время')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('paid_at')->label('Оплачен')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('accepted_at')->label('Принят')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('preparing_at')->label('В работе')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('ready_at')->label('Готов')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('completed_at')->label('Выдан')->dateTime('d.m.Y H:i')->placeholder('—'),
                        TextEntry::make('created_at')->label('Создан')->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(3),
            ]);
    }
}
