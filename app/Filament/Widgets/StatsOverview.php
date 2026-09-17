<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderFeedback;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $activeCoffee = Order::query()
            ->ofType(OrderType::Venue)
            ->whereIn('status', [
                OrderStatus::Accepted->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
            ])
            ->count();

        $todayTotal = (int) Order::query()
            ->whereDate('created_at', today())
            ->sum('total');

        return [
            Stat::make('Активные заказы', (string) $activeCoffee)
                ->description('Кофейня · в работе')
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),
            Stat::make('Выручка сегодня', Money::format($todayTotal))
                ->description('Все точки')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Клиенты', (string) User::query()->count())
                ->description('Приложение')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
            Stat::make('Оценки', (string) OrderFeedback::query()->count())
                ->description('Отзывы гостей')
                ->descriptionIcon('heroicon-m-hand-thumb-up')
                ->color('warning'),
            Stat::make('Напитки в меню', (string) Product::query()->food()->active()->count())
                ->description('Активные позиции')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),
        ];
    }
}
