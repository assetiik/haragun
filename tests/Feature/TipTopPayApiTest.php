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
use App\Models\User;
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

    public function test_order_create_stays_mock_paid_when_tiptop_disabled(): void
    {
        config([
            'services.tiptop_pay.enabled' => false,
            'services.tiptop_pay.public_id' => null,
            'services.tiptop_pay.api_secret' => null,
        ]);

        [$user, $arena, $adult] = $this->seedArena();
        Sanctum::actingAs($user);

        $this->postJson('/api/orders', [
            'establishment_id' => $arena->id,
            'date' => now()->toDateString(),
            'time' => '16:00',
            'items' => [
                ['product_id' => $adult->id, 'quantity' => 1],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('payment', null);
    }

    public function test_order_create_awaits_payment_when_tiptop_enabled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

        $this->configureTipTop();
        $this->configureRinkApi();
        $this->fakeRinkApiCatalogAndOrder();

        [$user, $arena, $adult] = $this->seedArena();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders', [
            'establishment_id' => $arena->id,
            'date' => '2026-02-13',
            'time' => '16:00',
            'items' => [
                ['product_id' => $adult->id, 'quantity' => 1],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('order.payment_status', 'pending')
            ->assertJsonPath('order.status', 'accepted')
            ->assertJsonPath('payment.provider', 'tiptop')
            ->assertJsonPath('payment.required', true)
            ->assertJsonPath('payment.public_id', 'pk_test_public')
            ->assertJsonPath('payment.amount', 4000);

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('order.id'),
            'payment_status' => PaymentStatus::Pending->value,
            'payment_provider' => 'tiptop',
            'paid_at' => null,
        ]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/orders'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/confirm'));
    }

    public function test_charge_marks_order_paid_and_confirms_rink(): void
    {
        $this->configureTipTop();
        $this->configureRinkApi();

        [$user, $order] = $this->seedPendingTicketOrder();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $host = (string) parse_url($request->url(), PHP_URL_HOST);
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if ($host === 'api.tiptoppay.test' && str_ends_with($path, '/payments/cards/charge')) {
                return Http::response([
                    'Success' => true,
                    'Message' => null,
                    'Model' => [
                        'TransactionId' => 555001,
                        'Amount' => 4000,
                        'CardType' => 'Visa',
                        'CardLastFour' => '4242',
                        'Status' => 'Completed',
                    ],
                ]);
            }

            if ($host === 'rink.test' && $request->method() === 'POST' && str_ends_with($path, '/confirm')) {
                return Http::response([
                    'order_id' => 'rink_1',
                    'tickets' => [
                        [
                            'code' => 'ZYULLQB3QR5ZWZLN5LTT',
                            'ticket_type' => 'adult',
                            'price' => 4000,
                            'status' => 'active',
                        ],
                    ],
                ]);
            }

            return Http::response(['ok' => true]);
        });

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payments/charge", [
            'cryptogram' => str_repeat('A', 40),
            'name' => 'TEST USER',
        ])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.payment_label', 'Visa · 4242')
            ->assertJsonPath('order.status', 'confirmed')
            ->assertJsonPath('payment.required', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => PaymentStatus::Paid->value,
            'tiptop_transaction_id' => 555001,
        ]);

        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame(['ZYULLQB3QR5ZWZLN5LTT'], $order->items()->first()->fresh()->barcodes);
    }

    public function test_charge_returns_three_ds_challenge(): void
    {
        $this->configureTipTop();

        [$user, $order] = $this->seedPendingTicketOrder(withRink: false);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/payments/cards/charge')) {
                return Http::response([
                    'Success' => false,
                    'Message' => null,
                    'Model' => [
                        'TransactionId' => 891463508,
                        'PaReq' => 'pareq-test',
                        'AcsUrl' => 'https://demo.tiptoppay.kz/acs',
                        'IFrameIsAllowed' => true,
                    ],
                ]);
            }

            return Http::response(['ok' => true]);
        });

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payments/charge", [
            'cryptogram' => str_repeat('B', 40),
        ])
            ->assertOk()
            ->assertJsonPath('three_ds.transaction_id', 891463508)
            ->assertJsonPath('three_ds.acs_url', 'https://demo.tiptoppay.kz/acs')
            ->assertJsonPath('order.payment_status', 'pending');
    }

    public function test_pay_webhook_marks_order_paid(): void
    {
        $this->configureTipTop();

        [$user, $order] = $this->seedPendingTicketOrder(withRink: false);

        $payload = [
            'TransactionId' => 777,
            'Amount' => '4000.00',
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

    public function test_refund_pass_also_refunds_via_tiptop(): void
    {
        $this->configureTipTop();
        $this->configureRinkApi();

        [$user, $order] = $this->seedPaidTicketOrderWithPass();

        Http::fake([
            'https://rink.test/api/v1/tickets/ZYULLQB3QR5ZWZLN5LTT/refund' => Http::response([
                'code' => 'ZYULLQB3QR5ZWZLN5LTT',
                'status' => 'refunded',
                'price' => 4000,
                'can_refund' => false,
                'can_move' => false,
            ]),
            'https://api.tiptoppay.test/payments/refund' => Http::response([
                'Success' => true,
                'Message' => null,
                'Model' => ['TransactionId' => 999],
            ]),
            'https://rink.test/*' => Http::response(['ok' => true]),
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/passes/ZYULLQB3QR5ZWZLN5LTT/refund")
            ->assertOk()
            ->assertJsonPath('refunded_amount', 4000);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.tiptoppay.test/payments/refund'
            && (int) $request['TransactionId'] === 555001
            && (int) $request['Amount'] === 4000);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => PaymentStatus::Refunded->value,
            'payment_refunded_total' => 4000,
        ]);
    }

    private function configureTipTop(): void
    {
        config([
            'services.tiptop_pay.enabled' => true,
            'services.tiptop_pay.base_url' => 'https://api.tiptoppay.test',
            'services.tiptop_pay.public_id' => 'pk_test_public',
            'services.tiptop_pay.api_secret' => 'test_api_secret',
        ]);
    }

    private function hmac(string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, 'test_api_secret', true));
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product}
     */
    private function seedArena(): array
    {
        $user = User::factory()->create();
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
            'name' => 'Ледовая арена',
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
            'price' => 4000,
            'ticket_type' => 'adult',
        ]);

        return [$user, $arena, $adult];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function seedPendingTicketOrder(bool $withRink = true): array
    {
        [$user, $arena, $adult] = $this->seedArena();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $arena->id,
            'type' => OrderType::Tickets,
            'status' => OrderStatus::Accepted,
            'total' => 4000,
            'subtotal' => 4000,
            'payment_status' => PaymentStatus::Pending,
            'payment_provider' => 'tiptop',
            'payment_label' => null,
            'paid_at' => null,
            'rink_order_id' => $withRink ? 'rink_1' : null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $adult->id,
            'kind' => ProductKind::Ticket->value,
            'name' => 'Взрослый',
            'quantity' => 1,
            'unit_price' => 4000,
            'amount' => 4000,
            'barcodes' => null,
        ]);

        return [$user, $order];
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function seedPaidTicketOrderWithPass(): array
    {
        [$user, $arena, $adult] = $this->seedArena();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $arena->id,
            'type' => OrderType::Tickets,
            'status' => OrderStatus::Confirmed,
            'total' => 4000,
            'subtotal' => 4000,
            'payment_status' => PaymentStatus::Paid,
            'payment_provider' => 'tiptop',
            'tiptop_transaction_id' => 555001,
            'payment_refunded_total' => 0,
            'rink_order_id' => 'rink_1',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $adult->id,
            'kind' => ProductKind::Ticket->value,
            'name' => 'Взрослый',
            'quantity' => 1,
            'unit_price' => 4000,
            'amount' => 4000,
            'barcodes' => ['ZYULLQB3QR5ZWZLN5LTT'],
        ]);

        return [$user, $order];
    }
}
