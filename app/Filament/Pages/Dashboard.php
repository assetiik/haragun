<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Главная';

    protected static ?string $title = 'Обзор Haragun';

    protected static ?int $navigationSort = -1;
}
