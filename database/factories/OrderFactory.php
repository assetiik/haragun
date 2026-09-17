<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1000, 9999);

        return [
            'user_id' => User::factory(),
            'establishment_id' => Establishment::factory(),
            'type' => OrderType::Venue,
            'status' => OrderStatus::Preparing,
            'number' => $number,
            'guest_number' => fake()->numberBetween(1000, 9999),
            'code' => 'HRG-'.$number,
            'session_date' => now()->toDateString(),
            'session_time' => '16:00:00',
            'session_ends_at' => '17:00:00',
            'subtotal' => 10000,
            'discount_total' => 0,
            'total' => 10000,
            'items_count' => 2,
            'summary_title' => 'Капучино и пончик',
            'summary_short' => '2 позиции',
            'payment_label' => 'Kaspi Gold · 4417',
            'points_to_earn' => 500,
            'prep_minutes' => 5,
            'paid_at' => now(),
            'accepted_at' => now(),
            'preparing_at' => now(),
        ];
    }
}
