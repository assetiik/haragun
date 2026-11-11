<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ConfiguresRinkApi;
use Tests\TestCase;

class RinkApiBookingApiTest extends TestCase
{
    use ConfiguresRinkApi;
    use RefreshDatabase;

    public function test_establishment_slots_and_prices_come_from_rink_api(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder();

        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);

        $category = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'slug' => 'ice_rental',
            'requires_booking' => true,
        ]);

        Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Ticket,
            'name' => 'Взрослый',
            'ticket_type' => 'adult',
            'price' => 2500,
        ]);

        Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Ticket,
            'name' => 'Студенческий',
            'ticket_type' => 'student',
            'price' => 1500,
        ]);

        $this->getJson("/api/establishments/{$arena->id}?category=ice_rental&date=2026-02-13")
            ->assertOk()
            ->assertJsonPath('selected_date', '2026-02-13')
            ->assertJsonPath('slots.0.time', '16:00')
            ->assertJsonPath('slots.0.ends_at', '17:00')
            ->assertJsonPath('slots.0.is_available', true)
            ->assertJsonPath('slots.0.rink_session_id', 41);

        $this->getJson("/api/establishments/{$arena->id}?category=ice_rental&date=2026-02-13&time=16:00")
            ->assertOk()
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.name', 'Взрослый')
            ->assertJsonPath('products.0.price', 4000);
    }
}
