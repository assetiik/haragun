<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Kaspi = 'kaspi';

    public function label(): string
    {
        return match ($this) {
            self::Kaspi => 'Kaspi',
        };
    }

    public function logoKey(): string
    {
        return $this->value;
    }

    public function orderLabel(): string
    {
        return match ($this) {
            self::Kaspi => 'Kaspi',
        };
    }
}
