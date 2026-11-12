<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function isPaid(): bool
    {
        return in_array($this, [
            self::Paid,
            self::PartiallyRefunded,
            self::Refunded,
        ], true);
    }

    public function canCharge(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Failed,
        ], true);
    }
}
