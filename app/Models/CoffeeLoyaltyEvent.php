<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'order_id',
    'type',
    'stamps',
    'before_paid_stamps',
    'after_paid_stamps',
    'before_snapshot',
    'after_snapshot',
])]
class CoffeeLoyaltyEvent extends Model
{
    public const TYPE_EARN = 'earn';

    public const TYPE_REVERSE = 'reverse';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stamps' => 'integer',
            'before_paid_stamps' => 'integer',
            'after_paid_stamps' => 'integer',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
