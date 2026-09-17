<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isActive(OrderType $type): bool
    {
        return match ($type) {
            OrderType::Venue => in_array($this, [
                self::Accepted,
                self::Preparing,
                self::Ready,
            ], true),
            OrderType::Tickets => in_array($this, [
                self::Confirmed,
                self::Accepted,
            ], true),
        };
    }
}
