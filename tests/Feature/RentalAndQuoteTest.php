<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\City;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\SkateSize;
use App\Support\IceRentalCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RentalAndQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_rentals_endpoint_returns_skates_sizes_and_helmet(): void
    {
        [$arena, $skates, $helmet] = $this->seedArenaCatalog();

        $response = $this->getJson("/api/establishments/{$arena->id}/rentals?date=2026-02-14&time=16:00");

        $response
            ->assertOk()
            ->assertJsonPath('session.date', '2026-02-14')
            ->assertJsonPath('session.time', '16:00')
            ->assertJsonPath('rentals.0.id', $skates->id)
            ->assertJsonPath('rentals.0.kind', 'rental_skates')
            ->assertJsonPath('rentals.0.selection_mode', 'sizes')
            ->assertJsonPath('rentals.1.id', $helmet->id)
            ->assertJsonPath('rentals.1.kind', 'rental_helmet')
            ->assertJsonPath('rentals.1.selection_mode', 'quantity');

        $sizes = collect($response->json('rentals.0.sizes'));
        $this->assertTrue($sizes->contains(fn (array $size) => $size['size'] === 38 && $size['is_available'] === true));
        $this->assertTrue($sizes->contains(fn (array $size) => $size['size'] === 46 && $size['is_available'] === false));
    }

    public function test_rentals_endpoint_returns_child_skates_adult_skates_helmet_and_support(): void
    {
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
            'kind' => ProductKind::RentalSkates,
            'name' => 'Коньки',
            'price' => 2500,
            'requires_size' => true,
        ]);

        IceRentalCatalog::sync($category);

        $this->getJson("/api/establishments/{$arena->id}/rentals?date=2026-10-16&time=10:00")
            ->assertOk()
            ->assertJsonCount(4, 'rentals')
            ->assertJsonPath('rentals.0.name', 'Детские коньки (бренд GRAF)')
            ->assertJsonPath('rentals.0.kind', 'rental_skates')
            ->assertJsonPath('rentals.0.price', 2000)
            ->assertJsonPath('rentals.0.meta', '1 сеанс')
            ->assertJsonPath('rentals.0.size_min', 25)
            ->assertJsonPath('rentals.0.size_max', 35)
            ->assertJsonPath('rentals.0.selection_mode', 'sizes')
            ->assertJsonPath('rentals.0.sizes.0.size', 25)
            ->assertJsonPath('rentals.1.name', 'Взрослые коньки')
            ->assertJsonPath('rentals.1.price', 3000)
            ->assertJsonPath('rentals.1.size_min', 36)
            ->assertJsonPath('rentals.1.size_max', 47)
            ->assertJsonPath('rentals.1.sizes.11.size', 47)
            ->assertJsonPath('rentals.2.name', 'Шлем / защита')
            ->assertJsonPath('rentals.2.kind', 'rental_helmet')
            ->assertJsonPath('rentals.2.price', 1500)
            ->assertJsonPath('rentals.2.selection_mode', 'quantity')
            ->assertJsonPath('rentals.3.kind', 'rental_support')
            ->assertJsonPath('rentals.3.price', 2000)
            ->assertJsonPath('rentals.3.selection_mode', 'quantity')
            ->assertJsonPath('rentals.3.requires_size', false)
            ->assertJsonPath('rentals.3.sizes', []);
    }

    public function test_quote_calculates_tickets_rentals_and_weekday_discount(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        [$arena, $skates, $helmet, $adult] = $this->seedArenaCatalog();

        $response = $this->postJson('/api/orders/quote', [
            'establishment_id' => $arena->id,
            'date' => '2026-02-13',
            'time' => '16:00',
            'items' => [
                ['product_id' => $adult->id, 'quantity' => 2],
                ['product_id' => $skates->id, 'sizes' => [38, 41]],
                ['product_id' => $helmet->id, 'quantity' => 1],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('total', 10000)
            ->assertJsonPath('items_count', 5)
            ->assertJsonPath('lines.0.name', 'Взрослый')
            ->assertJsonPath('lines.0.quantity', 2)
            ->assertJsonPath('lines.1.name', 'Коньки')
            ->assertJsonPath('lines.1.quantity', 2)
            ->assertJsonPath('lines.1.sizes', [38, 41])
            ->assertJsonPath('lines.2.name', 'Шлем')
            ->assertJsonPath('lines.2.quantity', 1)
            ->assertJsonPath('lines.3.code', 'weekday_skate_discount')
            ->assertJsonPath('lines.3.amount', -2500)
            ->assertJsonPath('lines.3.is_discount', true)
            ->assertJsonPath('session.date', '2026-02-13')
            ->assertJsonPath('session.time', '16:00');
    }

    public function test_rental_and_quote_prices_follow_the_weekend(): void
    {
        [$arena, $skates, $helmet, $adult] = $this->seedArenaCatalog();

        $adult->update(['price' => 4000, 'weekend_price' => 6000]);
        $skates->update(['weekend_price' => 3500]);
        $helmet->update(['weekend_price' => 1500]);

        $this->getJson("/api/establishments/{$arena->id}/rentals?date=2026-02-13&time=10:00")
            ->assertOk()
            ->assertJsonPath('rentals.0.price', 2500)
            ->assertJsonPath('rentals.1.price', 2500);

        $this->getJson("/api/establishments/{$arena->id}/rentals?date=2026-02-14&time=10:00")
            ->assertOk()
            ->assertJsonPath('rentals.0.price', 3500)
            ->assertJsonPath('rentals.1.price', 1500);

        $this->postJson('/api/orders/quote', [
            'establishment_id' => $arena->id,
            'date' => '2026-02-14',
            'time' => '10:00',
            'items' => [
                ['product_id' => $adult->id, 'quantity' => 1],
                ['product_id' => $skates->id, 'sizes' => [38]],
                ['product_id' => $helmet->id, 'quantity' => 1],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('lines.0.unit_price', 6000)
            ->assertJsonPath('lines.1.unit_price', 3500)
            ->assertJsonPath('lines.2.unit_price', 1500)
            ->assertJsonPath('total', 11000);
    }

    public function test_quote_rejects_unavailable_skate_size(): void
    {
        [$arena, $skates] = $this->seedArenaCatalog();

        $this->postJson('/api/orders/quote', [
            'establishment_id' => $arena->id,
            'date' => '2026-02-14',
            'time' => '16:00',
            'items' => [
                ['product_id' => $skates->id, 'sizes' => [46]],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    }

    /**
     * @return array{0: Establishment, 1: Product, 2: Product, 3: Product}
     */
    private function seedArenaCatalog(): array
    {
        $city = City::factory()->create();
        $arena = Establishment::factory()->create([
            'city_id' => $city->id,
            'name' => 'Ледовая арена Haragun',
            'type' => EstablishmentType::IceArena,
            'booking_days_ahead' => 14,
        ]);

        $category = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'slug' => 'ice_rental',
            'requires_booking' => true,
        ]);

        $adult = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Ticket,
            'name' => 'Взрослый',
            'price' => 2500,
            'sort_order' => 1,
        ]);

        $skates = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::RentalSkates,
            'name' => 'Коньки',
            'price' => 2500,
            'requires_size' => true,
            'size_min' => 35,
            'size_max' => 46,
            'sort_order' => 10,
        ]);

        foreach (range(35, 46) as $size) {
            SkateSize::query()->create([
                'product_id' => $skates->id,
                'size' => $size,
                'is_available' => $size !== 46,
            ]);
        }

        $helmet = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::RentalHelmet,
            'name' => 'Шлем',
            'price' => 2500,
            'meta' => 'универсальный размер',
            'sort_order' => 11,
        ]);

        return [$arena, $skates, $helmet, $adult];
    }
}
