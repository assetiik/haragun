<?php

namespace Database\Factories;

use App\Enums\EstablishmentType;
use App\Models\Establishment;
use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'establishment_id' => Establishment::factory(),
            'type' => function (array $attributes): EstablishmentType|string {
                $establishmentId = $attributes['establishment_id'] ?? null;

                if (is_numeric($establishmentId)) {
                    return Establishment::query()->whereKey($establishmentId)->value('type')
                        ?? EstablishmentType::Coffee;
                }

                return EstablishmentType::Coffee;
            },
            'name' => $name,
            'slug' => fake()->unique()->slug(),
            'section_title' => fake()->sentence(2),
            'section_subtitle' => fake()->sentence(4),
            'requires_booking' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
