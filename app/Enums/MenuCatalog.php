<?php

namespace App\Enums;

enum MenuCatalog: string
{
    case Drinks = 'drinks';
    case Snacks = 'snacks';

    public function label(): string
    {
        return match ($this) {
            self::Drinks => 'Напитки',
            self::Snacks => 'Перекус',
        };
    }
}
