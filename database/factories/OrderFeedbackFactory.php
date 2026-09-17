<?php

namespace Database\Factories;

use App\Enums\FeedbackRating;
use App\Models\Order;
use App\Models\OrderFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderFeedback>
 */
class OrderFeedbackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'user_id' => User::factory(),
            'rating' => FeedbackRating::Positive,
            'likes' => ['taste'],
            'dislikes' => [],
            'comment' => null,
        ];
    }
}
