<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\SkateSize;
use App\Models\User;
use App\Services\TipTopPay\TipTopPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ConfiguresRinkApi;
use Tests\TestCase;

class TipTopPayApiTest extends TestCase
{
    use ConfiguresRinkApi;
    use RefreshDatabase;

    public function test_coffee_checkout_awaits_tiptop_when_enabled(): void
    {
        $this->configureTipTop();

        [$user, $establishment, $latte, $size] = $this->seedCoffee();
        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/checkout")
            ->assertCreated()
            ->assertJsonPath('order.payment_status', 'pending')
            ->assertJsonPath('order.payment_provider', 'tiptop')
            ->assertJsonPath('order.is_active', false)
            ->assertJsonPath('payment.provider', 'tiptop')
            ->assertJsonPath('payment.required', true)
            ->assertJsonPath('payment.public_id', 'pk_coffee_test')
            ->assertJsonPath('payment.amount', 2500)
            ->assertJsonPath('coffee_screen', null)
            ->assertJsonPath('cart.is_empty', false);

        $this->assertDatabaseHas('orders', [
            'establishment_id' => $establishment->id,
            'payment_status' => PaymentStatus::Pending->value,
            'payment_provider' => 'tiptop',
            'paid_at' => null,
            'accepted_at' => null,
        ]);
    }

    public function test_coffee_checkout_stays_mock_paid_when_coffee_merchant_missing(): void
    {
        $this->configureTipTop(ice: true, coffee: false);

        [$user, $establishment, $latte, $size] = $this->seedCoffee();
        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/checkout")
            ->assertCreated()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('payment', null)
            ->assertJsonPath('cart.is_empty', true);
    }

    public function test_ice_arena_cart_checkout_awaits_tiptop_when_enabled(): void
    {
        $this->configureTipTop();

        [$user, $establishment, $donut] = $this->seedIceFoodCourt();
        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $donut->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/checkout")
            ->assertCreated()
            ->assertJsonPath('order.payment_status', 'pending')
            ->assertJsonPath('order.payment_provider', 'tiptop')
            ->assertJsonPath('order.is_active', false)
            ->assertJsonPath('payment.provider', 'tiptop')
            ->assertJsonPath('payment.required', true)
            ->assertJsonPath('payment.public_id', 'pk_ice_test')
            ->assertJsonPath('payment.amount', 800)
            ->assertJsonPath('coffee_screen', null)
            ->assertJsonPath('cart.is_empty', false)
            ->assertJsonPath('cart.totals.total', 800);

        $this->assertDatabaseHas('orders', [
            'establishment_id' => $establishment->id,
            'payment_status' => PaymentStatus::Pending->value,
            'payment_provider' => 'tiptop',
            'paid_at' => null,
            'accepted_at' => null,
        ]);
    }

    public function test_charge_marks_coffee_order_paid_with_coffee_merchant(): void
    {
        $this->configureTipTop();

        [$user, $establishment, $latte, $size] = $this->seedCoffee();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $establishment->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Accepted,
            'total' => 2500,
            'subtotal' => 2500,
            'payment_status' => PaymentStatus::Pending,
            'payment_provider' => 'tiptop',
            'payment_label' => null,
            'paid_at' => null,
            'accepted_at' => null,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $latte->id,
            'kind' => ProductKind::Food->value,
            'name' => 'Латте',
            'quantity' => 1,
            'unit_price' => 2500,
            'amount' => 2500,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/payments/cards/charge')) {
                return Http::response([
                    'Success' => true,
                    'Message' => null,
                    'Model' => [
                        'TransactionId' => 555010,
                        'Amount' => 2500,
                        'CardType' => 'Visa',
                        'CardLastFour' => '4242',
                        'Status' => 'Completed',
                    ],
                ]);
            }

            return Http::response(['ok' => true]);
        });

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson("/api/orders/{$order->id}/payments/charge", [
            'cryptogram' => str_repeat('A', 40),
            'name' => 'TEST USER',
        ])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('payment.public_id', 'pk_coffee_test')
            ->assertJsonPath('coffee_screen.screen', 'accepted')
            ->assertJsonPath('cart.is_empty', true);
    }

    public function test_ticket_order_awaits_tiptop_and_defers_rink_confirm(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureTipTop();
        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder();

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
        ])
            ->assertCreated()
            ->assertJsonPath('order.payment_status', 'pending')
            ->assertJsonPath('order.payment_provider', 'tiptop')
            ->assertJsonPath('order.is_active', false)
            ->assertJsonPath('payment.required', true)
            ->assertJsonPath('payment.provider', 'tiptop')
            ->assertJsonPath('order.passes.0.barcode', null);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v1/orders');

        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST'
            && parse_url($request->url(), PHP_URL_PATH) === '/api/v1/orders/ord_test_hold_1/confirm');
    }

    public function test_charge_marks_ice_venue_order_paid(): void
    {
        $this->configureTipTop();

        [$user, $order, $donut] = $this->seedPendingIceVenueOrder();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/payments/cards/charge')) {
                return Http::response([
                    'Success' => true,
                    'Message' => null,
                    'Model' => [
                        'TransactionId' => 555001,
                        'Amount' => 800,
                        'CardType' => 'Visa',
                        'CardLastFour' => '4242',
                        'Status' => 'Completed',
                    ],
                ]);
            }

            return Http::response(['ok' => true]);
        });

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$order->establishment_id}/cart/items", [
            'product_id' => $donut->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson("/api/orders/{$order->id}/payments/charge", [
            'cryptogram' => str_repeat('A', 40),
            'name' => 'TEST USER',
        ])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.payment_label', 'Visa · 4242')
            ->assertJsonPath('order.is_active', true)
            ->assertJsonPath('payment.required', false)
            ->assertJsonPath('cart.is_empty', true)
            ->assertJsonPath('coffee_screen', null);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => PaymentStatus::Paid->value,
            'tiptop_transaction_id' => 555001,
        ]);

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertNotNull($order->fresh()->accepted_at);
    }

    public function test_charge_confirms_rink_ticket_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureTipTop();
        $this->configureRinkApi();

        [$user, $arena, $adult, $skates, $helmet] = $this->seedArena();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $url = $request->url();
            $path = (string) parse_url($url, PHP_URL_PATH);
            $date = (string) ($request['date'] ?? '2026-02-13');

            if (str_contains($url, 'api.tiptoppay.test') && str_contains($path, '/payments/cards/charge')) {
                return Http::response([
                    'Success' => true,
                    'Message' => null,
                    'Model' => [
                        'TransactionId' => 555002,
                        'Amount' => (int) ($request['Amount'] ?? 0),
                        'CardType' => 'Visa',
                        'CardLastFour' => '4242',
                        'Status' => 'Completed',
                    ],
                ]);
            }

            return match (true) {
                $request->method() === 'GET' && $path === '/api/v1/sessions' => Http::response([
                    'date' => $date,
                    'day_type' => 'weekday',
                    'sessions' => [[
                        'id' => 41,
                        'name' => ['ru' => 'Сеанс 1', 'en' => 'Session 1'],
                        'starts_at' => $date.'T16:00:00+05:00',
                        'ends_at' => $date.'T17:00:00+05:00',
                        'entry_opens_at' => $date.'T15:00:00+05:00',
                        'entry_closes_at' => $date.'T17:00:00+05:00',
                        'status' => 'open',
                        'capacity' => 400,
                        'remaining' => 372,
                        'sale_state' => 'on_sale',
                        'on_sale' => true,
                        'prices' => [
                            ['ticket_type' => 'adult', 'name' => ['ru' => 'Взрослый', 'en' => 'Adult'], 'price' => 4000],
                            ['ticket_type' => 'child', 'name' => ['ru' => 'Детский', 'en' => 'Child'], 'price' => 2000],
                        ],
                    ]],
                ]),
                $request->method() === 'POST' && $path === '/api/v1/orders' => Http::response([
                    'order_id' => 'ord_test_hold_1',
                    'status' => 'held',
                    'total' => (int) ($request['expected_total'] ?? 8000),
                    'tickets' => [],
                ], 201),
                $request->method() === 'POST' && $path === '/api/v1/orders/ord_test_hold_1/confirm' => Http::response([
                    'order_id' => 'ord_test_hold_1',
                    'tickets' => [
                        ['ticket_type' => 'adult', 'code' => 'ZYULLQB3QR5ZWZLN5LTT'],
                        ['ticket_type' => 'adult', 'code' => 'BUH4853QM27CP3455D9A'],
                    ],
                ]),
                default => Http::response(['ok' => true]),
            };
        });

        Sanctum::actingAs($user);

        $create = $this->postJson('/api/orders', [
            'establishment_id' => $arena->id,
            'date' => '2026-02-13',
            'time' => '16:00',
            'items' => [
                ['product_id' => $adult->id, 'quantity' => 2],
                ['product_id' => $skates->id, 'sizes' => [38, 41]],
                ['product_id' => $helmet->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $orderId = (int) $create->json('order.id');
        $externalOrderId = (string) Order::query()->whereKey($orderId)->value('rink_external_order_id');

        $this->assertNotSame('', $externalOrderId);
        $this->assertSame($externalOrderId, $create->json('order.payment.invoice_id'));

        $this->postJson("/api/orders/{$orderId}/payments/charge", [
            'cryptogram' => str_repeat('A', 40),
            'name' => 'TEST USER',
        ])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.is_active', true)
            ->assertJsonPath('order.passes.0.barcode', 'ZYULLQB3QR5ZWZLN5LTT')
            ->assertJsonCount(2, 'order.passes');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), 'api.tiptoppay.test')
            && str_contains((string) parse_url($request->url(), PHP_URL_PATH), '/payments/cards/charge')
            && $request['InvoiceId'] === $externalOrderId
            && is_array($request['JsonData'] ?? null)
            && is_array(data_get($request['JsonData'], 'PaymentData.CustomerReceipt.Items')));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/api/v1/orders/ord_test_hold_1/confirm'));
    }

    public function test_pay_webhook_resolves_ticket_order_by_external_invoice_id(): void
    {
        $this->configureTipTop();

        [$user, $order] = $this->seedPendingIceVenueOrder();
        $order->forceFill([
            'rink_external_order_id' => 'APP-01jwebhookmatch',
        ])->save();

        $payload = [
            'TransactionId' => 888,
            'Amount' => (string) $order->total,
            'Currency' => 'KZT',
            'InvoiceId' => 'APP-01jwebhookmatch',
            'AccountId' => (string) $user->id,
            'CardType' => 'Visa',
            'CardLastFour' => '1111',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/payments/tiptop/pay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_CONTENT_HMAC' => $this->hmac($body),
            ],
            $body,
        )
            ->assertOk()
            ->assertExactJson(['code' => 0]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => PaymentStatus::Paid->value,
            'tiptop_transaction_id' => 888,
        ]);
    }

    public function test_pay_webhook_marks_ice_order_paid(): void
    {
        $this->configureTipTop();

        [$user, $order] = $this->seedPendingIceVenueOrder();

        $payload = [
            'TransactionId' => 777,
            'Amount' => '800.00',
            'Currency' => 'KZT',
            'InvoiceId' => (string) $order->id,
            'AccountId' => (string) $user->id,
            'CardType' => 'MasterCard',
            'CardLastFour' => '4444',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/payments/tiptop/pay',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_CONTENT_HMAC' => $this->hmac($body),
            ],
            $body,
        )
            ->assertOk()
            ->assertExactJson(['code' => 0]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => PaymentStatus::Paid->value,
            'tiptop_transaction_id' => 777,
            'payment_label' => 'MasterCard · 4444',
        ]);
    }

    public function test_webhook_rejects_invalid_hmac(): void
    {
        $this->configureTipTop();

        $this->postJson('/api/payments/tiptop/pay', [
            'TransactionId' => 1,
            'Amount' => 100,
            'InvoiceId' => '1',
        ], [
            'Content-HMAC' => 'invalid',
        ])
            ->assertOk()
            ->assertExactJson(['code' => 13]);
    }

    public function test_refund_sends_customer_receipt_json_data(): void
    {
        $this->configureTipTop();

        [$user, $order] = $this->seedPendingIceVenueOrder();
        $order->forceFill([
            'payment_status' => PaymentStatus::Paid,
            'tiptop_transaction_id' => 999001,
            'paid_at' => now(),
        ])->save();

        Http::preventStrayRequests();
        Http::fake([
            'api.tiptoppay.test/*' => Http::response([
                'Success' => true,
                'Message' => null,
                'Model' => ['TransactionId' => 999002],
            ]),
        ]);

        app(TipTopPayService::class)->refundOrderAmount($order->fresh(), 800);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/payments/refund')
            && (int) $request['TransactionId'] === 999001
            && (int) $request['Amount'] === 800
            && is_array(data_get($request['JsonData'], 'PaymentData.CustomerReceipt.Items'))
            && (float) data_get($request['JsonData'], 'PaymentData.CustomerReceipt.Amounts.Electronic') === 800.0);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => PaymentStatus::Refunded->value,
            'payment_refunded_total' => 800,
        ]);

        $this->assertSame($user->id, $order->fresh()->user_id);
    }

    private function configureTipTop(bool $ice = true, bool $coffee = true): void
    {
        config([
            'services.tiptop_pay.enabled' => true,
            'services.tiptop_pay.base_url' => 'https://api.tiptoppay.test',
            'services.tiptop_pay.public_id' => null,
            'services.tiptop_pay.api_secret' => null,
            'services.tiptop_pay.merchants.ice.public_id' => $ice ? 'pk_ice_test' : null,
            'services.tiptop_pay.merchants.ice.api_secret' => $ice ? 'ice_api_secret' : null,
            'services.tiptop_pay.merchants.coffee.public_id' => $coffee ? 'pk_coffee_test' : null,
            'services.tiptop_pay.merchants.coffee.api_secret' => $coffee ? 'coffee_api_secret' : null,
        ]);
    }

    private function hmac(string $body, string $secret = 'ice_api_secret'): string
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product, 3: ProductSize}
     */
    private function seedCoffee(EstablishmentType $type = EstablishmentType::Coffee): array
    {
        $user = User::factory()->create();
        $establishment = Establishment::factory()->create([
            'type' => $type,
            'name' => $type === EstablishmentType::Coffee ? 'Кофейня' : 'Арена',
        ]);
        $category = MenuCategory::factory()->create([
            'establishment_id' => $establishment->id,
            'slug' => 'coffee',
        ]);
        $latte = Product::factory()->create([
            'establishment_id' => $establishment->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте',
            'price' => 2000,
            'requires_size' => true,
        ]);
        $size = ProductSize::factory()->create([
            'product_id' => $latte->id,
            'label' => '400 мл',
            'price' => 2500,
        ]);

        return [$user, $establishment, $latte, $size];
    }

    /**
     * @return array{0: User, 1: Order, 2: Product}
     */
    private function seedPendingIceVenueOrder(): array
    {
        [$user, $establishment, $donut] = $this->seedIceFoodCourt();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $establishment->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Accepted,
            'total' => 800,
            'subtotal' => 800,
            'payment_status' => PaymentStatus::Pending,
            'payment_provider' => 'tiptop',
            'payment_label' => null,
            'paid_at' => null,
            'accepted_at' => null,
            'preparing_at' => null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $donut->id,
            'kind' => ProductKind::Food->value,
            'name' => 'Пончик',
            'quantity' => 1,
            'unit_price' => 800,
            'amount' => 800,
        ]);

        return [$user, $order->fresh(['establishment', 'items']), $donut];
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product}
     */
    private function seedIceFoodCourt(): array
    {
        $user = User::factory()->create();
        $establishment = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
            'name' => 'Арена',
        ]);
        $foodcourt = MenuCategory::factory()->create([
            'establishment_id' => $establishment->id,
            'slug' => 'foodcourt',
            'name' => 'Фудкорт',
        ]);
        $donut = Product::factory()->create([
            'establishment_id' => $establishment->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Пончик',
            'price' => 800,
            'food_section' => 'Перекус',
        ]);

        return [$user, $establishment, $donut];
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
