<?php

namespace App\Enums;

enum ProductKind: string
{
    case Ticket = 'ticket';
    case RentalSkates = 'rental_skates';
    case RentalHelmet = 'rental_helmet';
    case Food = 'food';

    public function isRental(): bool
    {
        return in_array($this, [self::RentalSkates, self::RentalHelmet], true);
    }

    public function chargesByDay(): bool
    {
        return $this !== self::Food;
    }
}
