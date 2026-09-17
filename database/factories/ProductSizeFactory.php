<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSize>
 */
class ProductSizeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'code' => fake()->randomElement(['S', 'M', 'L']),
            'label' => fake()->randomElement(['300 мл', '400 мл', '500 мл']),
            'volume_ml' => fake()->randomElement([300, 400, 500]),
            'price' => fake()->numberBetween(900, 2500),
            'is_default' => false,
            'sort_order' => 0,
        ];
    }
}
