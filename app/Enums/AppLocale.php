<?php

namespace App\Enums;

enum AppLocale: string
{
    case Ru = 'ru';
    case Kz = 'kz';
    case En = 'en';

    public function label(): string
    {
        return match ($this) {
            self::Ru => 'Русский',
            self::Kz => 'Қазақша',
            self::En => 'English',
        };
    }
}
