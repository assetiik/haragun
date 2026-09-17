<?php

namespace Database\Factories;

use App\Enums\EstablishmentType;
use App\Models\Brand;
use App\Models\City;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'city_id' => City::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'type' => EstablishmentType::Coffee,
            'icon_key' => 'coffee',
            'accent_color' => '#C26E40',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
