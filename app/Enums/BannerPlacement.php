<?php

namespace App\Enums;

enum BannerPlacement: string
{
    case Hero = 'hero';
    case Promo = 'promo';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Шапка (header)',
            self::Promo => 'Промо-плитки',
        };
    }
}
