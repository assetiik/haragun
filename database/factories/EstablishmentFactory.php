<?php

namespace Database\Factories;

use App\Enums\EstablishmentType;
use App\Models\City;
use App\Models\Establishment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Establishment>
 */
class EstablishmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'city_id' => City::factory(),
            'brand_id' => null,
            'name' => fake()->company(),
            'address' => fake()->streetAddress(),
            'phone' => '7'.fake()->numerify('##########'),
            'type' => fake()->randomElement(EstablishmentType::cases()),
            'latitude' => fake()->latitude(43.1, 43.3),
            'longitude' => fake()->longitude(76.8, 77.0),
            'opens_at' => '10:00:00',
            'closes_at' => '22:00:00',
            'parking_is_free' => false,
            'session_duration_minutes' => 60,
            'slot_interval_minutes' => 120,
            'ice_temperature' => null,
            'ice_note' => null,
            'header_color' => '#6B4EFF',
            'booking_days_ahead' => 7,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
