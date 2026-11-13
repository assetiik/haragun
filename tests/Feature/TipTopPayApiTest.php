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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipTopPayApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_coffee_checkout_stays_mock_paid_when_tiptop_disabled(): void
    {
        config([
            'services.tiptop_pay.enabled' => false,
            'services.tiptop_pay.public_id' => null,
            'services.tiptop_pay.api_secret' => null,
        ]);

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
            ->assertJsonPath('payment', null);
    }

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
            ->assertJsonPath('payment.provider', 'tiptop')
            ->assertJsonPath('payment.required', true)
            ->assertJsonPath('payment.public_id', 'pk_test_public')
            ->assertJsonPath('payment.amount', 2500);

        $this->assertDatabaseHas('orders', [
            'establishment_id' => $establishment->id,
            'payment_status' => PaymentStatus::Pending->value,
            'payment_provider' => 'tiptop',
            'paid_at' => null,
        ]);
    }

    public function test_charge_rejects_ice_arena_venue_order(): void
    {
        $this->configureTipTop();

        $user = User::factory()->create();
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $arena->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Accepted,
            'total' => 2500,
            'payment_status' => PaymentStatus::Pending,
            'payment_provider' => 'tiptop',
            'paid_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payments/charge", [
            'cryptogram' => str_repeat('A', 40),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment']);
    }

    public function test_ticket_order_ignores_tiptop(): void
    {
        $this->configureTipTop();

        $user = User::factory()->create();
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
            'price' => 4000,
            'ticket_type' => 'adult',
        ]);

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
            ->assertJsonMissingPath('payment.required');
    }

    public function test_charge_marks_coffee_order_paid(): void
    {
        $this->configureTipTop();

        [$user, $order] = $this->seedPendingCoffeeOrder();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/payments/cards/charge')) {
                return Http::response([
                    'Success' => true,
                    'Message' => null,
                    'Model' => [
                        'TransactionId' => 555001,
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

        $this->postJson("/api/orders/{$order->id}/payments/charge", [
            'cryptogram' => str_repeat('A', 40),
            'name' => 'TEST USER',
        ])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.payment_label', 'Visa · 4242')
            ->assertJsonPath('payment.required', false);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => PaymentStatus::Paid->value,
            'tiptop_transaction_id' => 555001,
        ]);
    }

    public function test_charge_rejects_ticket_order(): void
    {
        $this->configureTipTop();

        $user = User::factory()->create();
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $arena->id,
            'type' => OrderType::Tickets,
            'status' => OrderStatus::Confirmed,
            'total' => 4000,
            'payment_status' => PaymentStatus::Pending,
            'payment_provider' => 'tiptop',
            'paid_at' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payments/charge", [
            'cryptogram' => str_repeat('A', 40),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment']);
    }

    public function test_pay_webhook_marks_coffee_order_paid(): void
    {
        $this->configureTipTop();

        [$user, $order] = $this->seedPendingCoffeeOrder();

        $payload = [
            'TransactionId' => 777,
            'Amount' => '2500.00',
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
     * @return array{0: User, 1: Order}
     */
    private function seedPendingCoffeeOrder(): array
    {
        [$user, $establishment, $latte] = $this->seedCoffee();

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

        return [$user, $order->fresh(['establishment', 'items'])];
    }
}
