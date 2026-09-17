<?php

namespace Database\Factories;

use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'establishment_id' => Establishment::factory(),
            'menu_category_id' => MenuCategory::factory(),
            'kind' => ProductKind::Ticket,
            'requires_size' => false,
            'size_min' => null,
            'size_max' => null,
            'name' => fake()->words(2, true),
            'price' => fake()->numberBetween(500, 5000),
            'meta' => null,
            'description' => null,
            'image_url' => null,
            'background_color' => null,
            'is_popular' => false,
            'is_featured' => false,
            'food_section' => null,
            'icon_key' => null,
            'age_from' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
