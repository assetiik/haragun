<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'fi-fixed-positioning-context'])
            ->components([
                Section::make('Заказ')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('status')
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
                            )->all())
                            ->required()
                            ->native(false)
                            ->columnSpan([
                                'md' => 4,
                            ]),
                        TextInput::make('payment_label')
                            ->label('Оплата')
                            ->maxLength(255)
                            ->columnSpan([
                                'md' => 4,
                            ]),
                        TextInput::make('prep_minutes')
                            ->label('Минут на приготовление')
                            ->numeric()
                            ->minValue(0)
                            ->suffix('мин')
                            ->columnSpan([
                                'md' => 4,
                            ]),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
            ]);
    }
}
