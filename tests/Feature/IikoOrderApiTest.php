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
use Tests\Concerns\ConfiguresIiko;
use Tests\TestCase;

class IikoOrderApiTest extends TestCase
{
    use ConfiguresIiko;
    use RefreshDatabase;

    public function test_coffee_checkout_creates_iiko_order_when_mapped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureIiko();
        $this->fakeIikoTokenAndCreateOrder();

        [$user, $coffee, $latte] = $this->seedCoffee();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'establishment_id' => $coffee->id,
            'date' => '2026-02-13',
            'time' => '12:30',
            'items' => [
                ['product_id' => $latte->id, 'quantity' => 2],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('order.type', 'venue')
            ->assertJsonPath('order.total', 2400);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'establishment_id' => $coffee->id,
            'iiko_order_id' => '11111111-1111-1111-1111-111111111111',
            'iiko_correlation_id' => '22222222-2222-2222-2222-222222222222',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api-ru.iiko.services/api/1/order/create'
            && $request['organizationId'] === 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'
            && $request['terminalGroupId'] === 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'
            && $request['order']['tableIds'] === ['cccccccc-cccc-cccc-cccc-cccccccccccc']
            && $request['order']['items'][0]['productId'] === 'dddddddd-dddd-dddd-dddd-dddddddddddd'
            && $request['order']['items'][0]['amount'] === 2.0
            && $request['order']['payments'][0]['paymentTypeId'] === 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee'
            && $request['order']['payments'][0]['isProcessedExternally'] === true);
    }

    public function test_failed_iiko_create_does_not_keep_local_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureIiko();
        $this->fakeIikoTokenAndCreateOrder(createSucceeds: false);

        [$user, $coffee, $latte] = $this->seedCoffee();

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'establishment_id' => $coffee->id,
            'date' => '2026-02-13',
            'time' => '12:30',
            'items' => [
                ['product_id' => $latte->id, 'quantity' => 1],
            ],
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_coffee_checkout_skips_iiko_when_cafe_not_mapped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureIiko();
        Http::fake();

        [$user, $coffee, $latte] = $this->seedCoffee(mapped: false);

        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'establishment_id' => $coffee->id,
            'date' => '2026-02-13',
            'time' => '12:30',
            'items' => [
                ['product_id' => $latte->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'iiko_order_id' => null,
        ]);

        Http::assertNothingSent();
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product}
     */
    private function seedCoffee(bool $mapped = true): array
    {
        $user = User::factory()->create(['phone' => '77071234567']);
        $coffee = Establishment::factory()->create([
            'type' => EstablishmentType::Coffee,
            'iiko_organization_id' => $mapped ? 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa' : null,
            'iiko_terminal_group_id' => $mapped ? 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb' : null,
            'iiko_table_id' => $mapped ? 'cccccccc-cccc-cccc-cccc-cccccccccccc' : null,
            'iiko_payment_type_id' => $mapped ? 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee' : null,
        ]);

        $category = MenuCategory::factory()->create([
            'establishment_id' => $coffee->id,
            'type' => EstablishmentType::Coffee,
        ]);

        $latte = Product::factory()->create([
            'establishment_id' => $coffee->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте',
            'price' => 1200,
            'iiko_product_id' => $mapped ? 'dddddddd-dddd-dddd-dddd-dddddddddddd' : null,
        ]);

        return [$user, $coffee, $latte];
    }
}
