<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\City;
use App\Models\Establishment;
use App\Models\EstablishmentUnavailableSlot;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EstablishmentShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_arena_details_categories_products_and_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14 11:00:00'));

        $city = City::factory()->create();
        $arena = Establishment::factory()->create([
            'city_id' => $city->id,
            'name' => 'Ледовая арена Haragun',
            'address' => 'пр. Достык, 240',
            'type' => EstablishmentType::IceArena,
            'latitude' => 43.2335,
            'longitude' => 76.9560,
            'opens_at' => '10:00:00',
            'closes_at' => '22:00:00',
            'parking_is_free' => true,
            'session_duration_minutes' => 60,
            'slot_interval_minutes' => 120,
            'ice_temperature' => -9,
            'ice_note' => 'лёд свежий',
            'booking_days_ahead' => 5,
        ]);

        $ice = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'name' => 'Лёд и аренда',
            'slug' => 'ice_rental',
            'section_title' => 'Входной билет',
            'section_subtitle' => 'Сеанс 60 минут · забронируйте дату и время',
            'requires_booking' => true,
            'sort_order' => 1,
        ]);

        MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'name' => 'Перекус',
            'slug' => 'snack',
            'requires_booking' => false,
            'sort_order' => 2,
        ]);

        Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $ice->id,
            'name' => 'Взрослый',
            'price' => 2500,
            'meta' => 'от 13 лет',
            'age_from' => 13,
            'sort_order' => 1,
        ]);

        Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $ice->id,
            'name' => 'Детский',
            'price' => 1500,
            'meta' => 'до 12 лет',
            'sort_order' => 2,
        ]);

        EstablishmentUnavailableSlot::query()->create([
            'establishment_id' => $arena->id,
            'date' => '2026-09-14',
            'time' => '10:00:00',
        ]);

        $response = $this->getJson("/api/establishments/{$arena->id}?lat=43.238&lng=76.945");

        $response
            ->assertOk()
            ->assertJsonPath('establishment.name', 'Ледовая арена Haragun')
            ->assertJsonPath('establishment.status_label', 'Открыто до 22:00')
            ->assertJsonPath('establishment.ice_status_label', '-9 °C, лёд свежий')
            ->assertJsonPath('establishment.parking_is_free', true)
            ->assertJsonPath('selected_category.slug', 'ice_rental')
            ->assertJsonPath('selected_category.section_title', 'Входной билет')
            ->assertJsonPath('selected_date', '2026-09-14')
            ->assertJsonPath('products.0.name', 'Взрослый')
            ->assertJsonPath('products.0.price', 2500)
            ->assertJsonPath('products.0.subtitle', '2 500 ₸ · от 13 лет')
            ->assertJsonCount(5, 'dates')
            ->assertJsonPath('dates.0.weekday_short', 'пн')
            ->assertJsonPath('dates.0.day', 14);

        $slots = collect($response->json('slots'));
        $this->assertTrue($slots->contains(fn (array $slot) => $slot['time'] === '10:00' && $slot['is_available'] === false));
        $this->assertTrue($slots->contains(fn (array $slot) => $slot['time'] === '16:00' && $slot['is_available'] === true));
        $this->assertStringContainsString('парковка бесплатная', $response->json('establishment.location_meta'));
    }

    public function test_show_can_switch_to_foodcourt_category_without_slots(): void
    {
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);

        MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'slug' => 'ice_rental',
            'requires_booking' => true,
            'sort_order' => 1,
        ]);

        $foodcourt = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'name' => 'Фудкорт',
            'slug' => 'foodcourt',
            'requires_booking' => false,
            'sort_order' => 2,
        ]);

        Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Капучино',
            'price' => 1200,
            'meta' => '250 мл',
            'food_section' => 'Напитки',
        ]);

        $this->getJson("/api/establishments/{$arena->id}?category=foodcourt")
            ->assertOk()
            ->assertJsonPath('selected_category.slug', 'foodcourt')
            ->assertJsonPath('selected_category.is_foodcourt', true)
            ->assertJsonPath('sections.0.title', 'Напитки')
            ->assertJsonPath('products.0.name', 'Капучино')
            ->assertJsonPath('dates', [])
            ->assertJsonPath('slots', [])
            ->assertJsonPath('selected_date', null);
    }

    public function test_inactive_establishment_returns_not_found(): void
    {
        $establishment = Establishment::factory()->create([
            'is_active' => false,
        ]);

        $this->getJson("/api/establishments/{$establishment->id}")
            ->assertNotFound();
    }

    public function test_show_omits_distance_when_establishment_has_no_coordinates(): void
    {
        $establishment = Establishment::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->getJson("/api/establishments/{$establishment->id}?lat=43.238&lng=76.945")
            ->assertOk()
            ->assertJsonPath('establishment.latitude', null)
            ->assertJsonPath('establishment.longitude', null)
            ->assertJsonPath('establishment.distance_km', null)
            ->assertJsonPath('establishment.distance_label', null);
    }
}
