<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\SkateSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ConfiguresDssSoft;
use Tests\TestCase;

class DssSoftOrderApiTest extends TestCase
{
    use ConfiguresDssSoft;
    use RefreshDatabase;

    public function test_ticket_checkout_confirms_dss_order_and_returns_qr_barcodes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureDssSoft();
        $this->fakeDssSoftCatalogAndOrder();

        [$user, $arena, $adult, $skates, $helmet] = $this->seedArena();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
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
            ->assertCreated()
            ->assertJsonPath('order.type', 'tickets')
            ->assertJsonPath('order.session.date', '2026-02-13')
            ->assertJsonPath('order.session.time', '16:00')
            ->assertJsonPath('order.session.ends_at', '17:00')
            ->assertJsonPath('order.session.entry_opens_at', '15:00')
            ->assertJsonPath('order.passes.0.barcode', 'QR-ADULT-1')
            ->assertJsonPath('order.passes.1.barcode', 'QR-ADULT-2')
            ->assertJsonPath('order.passes.0.is_active', false)
            ->assertJsonCount(2, 'order.passes');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'dss_order_id' => 900,
            'dss_day_id' => 10,
            'dss_slot_id' => 3,
        ]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/partner/shows/195/order'
            && $request['dayId'] === 10
            && $request['slotId'] === 3
            && $request['cart'] === [
                ['pricegroup_id' => 41],
                ['pricegroup_id' => 41],
            ]
            && str_starts_with((string) $request['phone'], '+7'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/partner/order/900/confirm');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'DELETE');
    }

    public function test_failed_dss_confirm_cancels_hold_and_does_not_keep_local_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureDssSoft();
        $this->fakeDssSoftCatalogAndOrder(confirmSucceeds: false);

        [$user, $arena, $adult, $skates, $helmet] = $this->seedArena();

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'establishment_id' => $arena->id,
            'date' => '2026-02-13',
            'time' => '16:00',
            'items' => [
                ['product_id' => $adult->id, 'quantity' => 1],
                ['product_id' => $skates->id, 'sizes' => [38]],
                ['product_id' => $helmet->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && parse_url($request->url(), PHP_URL_PATH) === '/partner/order/900/cancel');
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product, 3: Product, 4: Product}
     */
    private function seedArena(): array
    {
        $user = User::factory()->create(['phone' => '77071234567']);
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);

        $category = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'slug' => 'ice_rental',
        ]);

        $adult = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Ticket,
            'name' => 'Взрослый',
            'price' => 2500,
        ]);

        $skates = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::RentalSkates,
            'name' => 'Коньки',
            'price' => 2500,
            'requires_size' => true,
        ]);

        SkateSize::query()->create([
            'product_id' => $skates->id,
            'size' => 38,
            'is_available' => true,
        ]);

        SkateSize::query()->create([
            'product_id' => $skates->id,
            'size' => 41,
            'is_available' => true,
        ]);

        $helmet = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::RentalHelmet,
            'name' => 'Шлем',
            'price' => 2500,
        ]);

        return [$user, $arena, $adult, $skates, $helmet];
    }
}
