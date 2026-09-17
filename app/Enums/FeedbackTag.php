<?php

namespace App\Enums;

enum FeedbackTag: string
{
    case Taste = 'taste';
    case Price = 'price';
    case WishesIgnored = 'wishes_ignored';
    case Underheated = 'underheated';
    case Overheated = 'overheated';

    public function label(): string
    {
        return match ($this) {
            self::Taste => 'Вкус',
            self::Price => 'Цена',
            self::WishesIgnored => 'Не учли пожелания',
            self::Underheated => 'Недогрели',
            self::Overheated => 'Перегрели',
        };
    }

    public function isPositiveOption(): bool
    {
        return in_array($this, [self::Taste, self::Price], true);
    }

    public function isNegativeOption(): bool
    {
        return true;
    }

    /**
     * @return list<self>
     */
    public static function likeOptions(): array
    {
        return [self::Taste, self::Price];
    }

    /**
     * @return list<self>
     */
    public static function dislikeOptions(): array
    {
        return [
            self::Taste,
            self::Price,
            self::WishesIgnored,
            self::Underheated,
            self::Overheated,
        ];
    }
}
