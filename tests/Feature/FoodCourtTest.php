<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
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
            ->assertJsonPath('badges.1.label', 'Готовим за 5 минут')
            ->assertJsonPath('sections.0.title', 'Напитки')
            ->assertJsonPath('sections.0.products.0.name', 'Капучино')
            ->assertJsonPath('sections.0.products.0.subtitle', '1 200 ₸ · 250 мл')
            ->assertJsonPath('sections.1.title', 'Перекус')
            ->assertJsonPath('sections.1.products.0.name', 'Хот-дог')
            ->assertJsonPath('session.short_label', 'сб, 14 · 16:00');
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
            ->assertJsonPath('receipt_groups.0.title', 'Билеты и аренда')
            ->assertJsonPath('receipt_groups.0.amount', 5000)
            ->assertJsonPath('receipt_groups.1.title', 'Буфет')
            ->assertJsonPath('receipt_groups.1.amount', 2600)
            ->assertJsonPath('total', 7600)
            ->assertJsonPath('food_note', 'Заберёте у стойки — попадёт в тот же чек');
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
