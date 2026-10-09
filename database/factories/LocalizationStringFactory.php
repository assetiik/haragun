<?php

namespace Database\Factories;

use App\Models\LocalizationString;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalizationString>
 */
class LocalizationStringFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'section' => 'common',
            'key' => fake()->unique()->lexify('key_????'),
            'ru' => fake()->sentence(3),
            'kz' => fake()->sentence(3),
            'en' => fake()->sentence(3),
        ];
    }
}
