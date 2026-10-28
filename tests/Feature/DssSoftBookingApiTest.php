<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\ConfiguresDssSoft;
use Tests\TestCase;

class DssSoftBookingApiTest extends TestCase
{
    use ConfiguresDssSoft;
    use RefreshDatabase;

    public function test_arena_booking_uses_dss_days_and_slots_when_configured(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureDssSoft();
        $this->fakeDssSoftCatalogAndOrder();

        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);

        $ice = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'slug' => 'ice_rental',
            'requires_booking' => true,
        ]);

        Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $ice->id,
            'kind' => ProductKind::Ticket,
            'name' => 'Взрослый',
            'price' => 2500,
            'weekend_price' => 6000,
        ]);

        $this->getJson("/api/establishments/{$arena->id}?category=ice_rental")
            ->assertOk()
            ->assertJsonPath('selected_date', '2026-02-13')
            ->assertJsonPath('dates.0.date', '2026-02-13')
            ->assertJsonPath('dates.0.dss_day_id', 10)
            ->assertJsonPath('dates.1.date', '2026-02-14')
            ->assertJsonCount(2, 'dates')
            ->assertJsonPath('slots.0.time', '16:00')
            ->assertJsonPath('slots.0.ends_at', '17:00')
            ->assertJsonPath('slots.0.dss_slot_id', 3)
            ->assertJsonPath('slots.0.is_available', true)
            ->assertJsonPath('products.0.price', 2500);

        $this->getJson("/api/establishments/{$arena->id}?category=ice_rental&date=2026-02-13&time=16:00")
            ->assertOk()
            ->assertJsonPath('products.0.price', 4000);
    }
}
