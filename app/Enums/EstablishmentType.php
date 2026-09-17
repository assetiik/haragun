<?php

namespace App\Enums;

enum EstablishmentType: string
{
    case IceArena = 'ice_arena';
    case Coffee = 'coffee';

    public function label(): string
    {
        return match ($this) {
            self::IceArena => 'Ледовая арена',
            self::Coffee => 'Кофейня',
        };
    }
}
