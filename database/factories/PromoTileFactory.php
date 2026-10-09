<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\PromoTile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoTile>
 */
class PromoTileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'title' => fake()->sentence(3),
            'image_url' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
