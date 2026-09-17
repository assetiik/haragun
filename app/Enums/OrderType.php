<?php

namespace App\Enums;

enum OrderType: string
{
    case Venue = 'venue';
    case Tickets = 'tickets';

    public function tabTitle(): string
    {
        return match ($this) {
            self::Venue => 'Из заведений',
            self::Tickets => 'Билеты',
        };
    }
}
