<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\Establishment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'establishment_id' => Establishment::factory(),
            'payment_method' => PaymentMethod::Kaspi,
        ];
    }
}
