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
use Tests\Concerns\ConfiguresRinkApi;
use Tests\TestCase;

class RinkApiOrderApiTest extends TestCase
{
    use ConfiguresRinkApi;
    use RefreshDatabase;

    public function test_ticket_checkout_confirms_rink_order_and_returns_qr_barcodes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder();

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
            ->assertJsonPath('order.passes.0.barcode', 'ZYULLQB3QR5ZWZLN5LTT')
            ->assertJsonPath('order.passes.1.barcode', 'BUH4853QM27CP3455D9A')
            ->assertJsonCount(2, 'order.passes');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'rink_order_id' => 'ord_test_hold_1',
            'rink_session_id' => 41,
        ]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v1/orders'
            && $request['session_id'] === 41
            && $request['sale_kind'] === 'online'
            && $request['items'] === [
                ['ticket_type' => 'adult', 'quantity' => 2, 'unit_price' => 4000],
            ]
            && str_starts_with((string) $request['external_order_id'], 'APP-'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v1/orders/ord_test_hold_1/confirm');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/release'));
    }

    public function test_failed_rink_confirm_releases_hold_and_does_not_keep_local_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder(confirmSucceeds: false);

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

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v1/orders/ord_test_hold_1/release');
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
            'ticket_type' => 'adult',
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
            'price' => 500,
        ]);

        return [$user, $arena, $adult, $skates, $helmet];
    }
}
