<?php

namespace App\Models;

use App\Enums\FeedbackRating;
use Database\Factories\OrderFeedbackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'user_id',
    'rating',
    'likes',
    'dislikes',
    'comment',
])]
class OrderFeedback extends Model
{
    /** @use HasFactory<OrderFeedbackFactory> */
    use HasFactory;

    protected $table = 'order_feedback';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => FeedbackRating::class,
            'likes' => 'array',
            'dislikes' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
