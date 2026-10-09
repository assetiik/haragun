<?php

namespace Database\Factories;

use App\Enums\BannerPlacement;
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
            'placement' => BannerPlacement::Hero,
            'title' => fake()->sentence(3),
            'subtitle' => fake()->sentence(4),
            'button_label' => 'Узнать больше →',
            'background_color' => '#6B4EFF',
            'image_url' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
