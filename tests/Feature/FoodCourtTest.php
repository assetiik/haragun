<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\MenuCatalog;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodCourtTest extends TestCase
{
    use RefreshDatabase;

    public function test_foodcourt_returns_sections_drinks_and_snacks(): void
    {
        [$arena] = $this->seedFoodCourt();

        $this->getJson("/api/establishments/{$arena->id}/foodcourt?date=2026-02-14&time=16:00")
            ->assertOk()
            ->assertJsonPath('header.title', 'Фудкорт')
            ->assertJsonPath('header.subtitle', 'Заберёте у стойки — попадёт в тот же чек')
            ->assertJsonPath('prep_minutes', 5)
            ->assertJsonPath('sections.0.key', 'Напитки')
            ->assertJsonPath('sections.0.products.0.name', 'Капучино')
            ->assertJsonPath('sections.0.products.0.price', 1200)
            ->assertJsonPath('sections.0.products.0.meta', '250 мл')
            ->assertJsonPath('sections.1.key', 'Перекус')
            ->assertJsonPath('sections.1.products.0.name', 'Хот-дог')
            ->assertJsonPath('session.date', '2026-02-14')
            ->assertJsonPath('session.time', '16:00');
    }

    public function test_foodcourt_includes_brand_meals_from_dish_categories(): void
    {
        [$arena] = $this->seedFoodCourt();
        $brand = Brand::factory()->create([
            'city_id' => $arena->city_id,
            'type' => EstablishmentType::IceArena,
        ]);
        $arena->update(['brand_id' => $brand->id]);

        $mealsCategory = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::IceArena,
            'catalog' => MenuCatalog::Snacks,
            'name' => 'Горячее',
            'slug' => 'goriacee',
        ]);
        Product::factory()->create([
            'brand_id' => $brand->id,
            'establishment_id' => null,
            'menu_category_id' => $mealsCategory->id,
            'kind' => ProductKind::Food,
            'name' => 'Сэндвич',
            'price' => 1800,
            'food_section' => 'Горячее',
            'sort_order' => 10,
        ]);

        $response = $this->getJson("/api/establishments/{$arena->id}/foodcourt");

        $response->assertOk();
        $names = collect($response->json('products'))->pluck('name');
        $this->assertTrue($names->contains('Сэндвич'));
        $this->assertTrue($names->contains('Хот-дог'));
        $sectionKeys = collect($response->json('sections'))->pluck('key');
        $this->assertTrue($sectionKeys->contains('Горячее'));
        $this->assertTrue($sectionKeys->contains('Перекус'));
    }

    public function test_quote_groups_buffet_separately(): void
    {
        [$arena, $adult, $cappuccino, $chocolate] = $this->seedFoodCourt();

        $this->postJson('/api/orders/quote', [
            'establishment_id' => $arena->id,
            'date' => '2026-02-14',
            'time' => '16:00',
            'items' => [
                ['product_id' => $adult->id, 'quantity' => 2],
                ['product_id' => $cappuccino->id, 'quantity' => 1],
                ['product_id' => $chocolate->id, 'quantity' => 1],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('receipt_groups.0.key', 'tickets_and_rentals')
            ->assertJsonPath('receipt_groups.0.amount', 5000)
            ->assertJsonPath('receipt_groups.1.key', 'buffet')
            ->assertJsonPath('receipt_groups.1.amount', 2600)
            ->assertJsonPath('total', 7600);
    }

    /**
     * @return array{0: Establishment, 1: Product, 2: Product, 3: Product}
     */
    private function seedFoodCourt(): array
    {
        $city = City::factory()->create();
        $arena = Establishment::factory()->create([
            'city_id' => $city->id,
            'name' => 'Ледовая арена Haragun',
            'type' => EstablishmentType::IceArena,
            'opens_at' => '10:00:00',
            'closes_at' => '22:00:00',
        ]);

        $ice = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'slug' => 'ice_rental',
            'name' => 'Лёд и аренда',
            'requires_booking' => true,
            'sort_order' => 1,
        ]);

        $foodcourt = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'slug' => 'foodcourt',
            'name' => 'Фудкорт',
            'header_title' => 'Фудкорт',
            'header_subtitle' => 'Заберёте у стойки — попадёт в тот же чек',
            'header_color' => '#1B2A4A',
            'prep_minutes' => 5,
            'requires_booking' => false,
            'sort_order' => 2,
        ]);

        $adult = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $ice->id,
            'kind' => ProductKind::Ticket,
            'name' => 'Взрослый',
            'price' => 2500,
        ]);

        $cappuccino = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Капучино',
            'price' => 1200,
            'meta' => '250 мл',
            'food_section' => 'Напитки',
            'icon_key' => 'coffee',
            'sort_order' => 1,
        ]);

        Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Американо',
            'price' => 900,
            'meta' => '300 мл',
            'food_section' => 'Напитки',
            'sort_order' => 2,
        ]);

        $chocolate = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Горячий шоколад',
            'price' => 1400,
            'meta' => 'с маршмеллоу',
            'food_section' => 'Напитки',
            'sort_order' => 3,
        ]);

        Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Хот-дог',
            'price' => 1500,
            'food_section' => 'Перекус',
            'sort_order' => 4,
        ]);

        return [$arena, $adult, $cappuccino, $chocolate];
    }
}
