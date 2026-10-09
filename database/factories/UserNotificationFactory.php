<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Establishment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserNotification>
 */
class UserNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'order_id' => null,
            'establishment_id' => Establishment::factory(),
            'type' => 'order_status',
            'status' => OrderStatus::Ready,
            'order_number' => fake()->numberBetween(1000, 9999),
            'order_type' => OrderType::Venue,
            'read_at' => null,
        ];
    }
}
