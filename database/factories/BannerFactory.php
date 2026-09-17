<?php

namespace Database\Factories;

use App\Models\Banner;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'title' => fake()->sentence(3),
            'subtitle' => fake()->sentence(4),
            'background_color' => '#6B4EFF',
            'image_url' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
