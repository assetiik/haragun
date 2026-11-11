<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ConfiguresRinkApi;
use Tests\TestCase;

class IceTicketPromoApiTest extends TestCase
{
    use ConfiguresRinkApi;
    use RefreshDatabase;

    public function test_preview_promo_returns_discounted_prices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder();

        $arena = Establishment::factory()->create(['type' => EstablishmentType::IceArena]);

        $this->getJson("/api/establishments/{$arena->id}/promo-codes/TEST10?date=2026-02-13&time=16:00")
            ->assertOk()
            ->assertJsonPath('promo.code', 'TEST10')
            ->assertJsonPath('promo.prices.1.ticket_type', 'adult')
            ->assertJsonPath('promo.prices.1.price', 3600)
            ->assertJsonPath('promo.prices.1.full_price', 4000);
    }

    public function test_checkout_with_promo_code_sends_discounted_unit_prices(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder();

        [$user, $arena, $adult] = $this->seedArena();

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'establishment_id' => $arena->id,
            'date' => '2026-02-13',
            'time' => '16:00',
            'promo_code' => 'TEST10',
            'items' => [
                ['product_id' => $adult->id, 'quantity' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('order.promo_code', 'TEST10')
            ->assertJsonPath('order.promo_discount', 400)
            ->assertJsonPath('order.total', 3600);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v1/orders'
            && $request['promo_code'] === 'TEST10'
            && $request['items'] === [
                ['ticket_type' => 'adult', 'quantity' => 1, 'unit_price' => 3600],
            ]);
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product}
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

        return [$user, $arena, $adult];
    }
}
