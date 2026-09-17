<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $product = Product::factory();

        return [
            'cart_id' => Cart::factory(),
            'product_id' => $product,
            'product_size_id' => null,
            'modifier_ids' => [],
            'signature' => fake()->unique()->sha256(),
            'quantity' => 1,
            'unit_price' => fake()->numberBetween(900, 2500),
        ];
    }
}
