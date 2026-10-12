<?php

namespace App\Enums;

enum ProductKind: string
{
    case Ticket = 'ticket';
    case RentalSkates = 'rental_skates';
    case RentalHelmet = 'rental_helmet';
    case RentalSupport = 'rental_support';
    case Food = 'food';

    public function isRental(): bool
    {
        return in_array($this, [self::RentalSkates, self::RentalHelmet, self::RentalSupport], true);
    }

    /**
     * @return list<string>
     */
    public static function rentalValues(): array
    {
        return array_values(array_map(
            fn (self $kind): string => $kind->value,
            array_filter(self::cases(), fn (self $kind): bool => $kind->isRental()),
        ));
    }

    public function chargesByDay(): bool
    {
        return $this !== self::Food;
    }
}
