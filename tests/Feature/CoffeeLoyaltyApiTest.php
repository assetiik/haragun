<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\City;
use App\Models\CoffeeLoyaltyCard;
use App\Models\CoffeeLoyaltyEvent;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use App\Services\CoffeeLoyaltyService;
use App\Services\TipTopPay\TipTopPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CoffeeLoyaltyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_and_menu_return_null_coffee_loyalty(): void
    {
        [$establishment] = $this->seedCoffeeVenue();

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('coffee_loyalty', null);

        $this->getJson("/api/establishments/{$establishment->id}/menu")
            ->assertOk()
            ->assertJsonPath('coffee_loyalty', null);
    }

    public function test_authenticated_guest_sees_zero_stamp_progress(): void
    {
        [$establishment, $cappuccino] = $this->seedCoffeeVenue();
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.paid_stamps', 0)
            ->assertJsonPath('coffee_loyalty.total_slots', 10)
            ->assertJsonPath('coffee_loyalty.stamps_until_reward', 9)
            ->assertJsonPath('coffee_loyalty.reward_available', false);

        $this->getJson("/api/establishments/{$establishment->id}/menu")
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.paid_stamps', 0)
            ->assertJsonPath('coffee_loyalty.reward_available', false);

        $this->getJson("/api/establishments/{$establishment->id}/menu/products/{$cappuccino->id}")
            ->assertOk()
            ->assertJsonPath('counts_toward_coffee_card', true);
    }

    public function test_checkout_awards_stamps_and_exposes_order_snapshots(): void
    {
        [$establishment, $cappuccino, $croissant] = $this->seedCoffeeVenue();
        $user = User::factory()->create();
        $size = ProductSize::factory()->create([
            'product_id' => $cappuccino->id,
            'code' => 'M',
            'label' => '400 мл',
            'price' => 1900,
            'is_default' => true,
        ]);

        CoffeeLoyaltyCard::query()->create([
            'user_id' => $user->id,
            'paid_stamps' => 7,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $cappuccino->id,
            'size_id' => $size->id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $croissant->id,
            'quantity' => 1,
        ])->assertOk();

        $response = $this->postJson("/api/establishments/{$establishment->id}/cart/checkout");

        $response
            ->assertCreated()
            ->assertJsonPath('order.coffee_loyalty.stamps_earned', 2)
            ->assertJsonPath('order.coffee_loyalty.before.paid_stamps', 7)
            ->assertJsonPath('order.coffee_loyalty.before.stamps_until_reward', 2)
            ->assertJsonPath('order.coffee_loyalty.before.reward_available', false)
            ->assertJsonPath('order.coffee_loyalty.after.paid_stamps', 9)
            ->assertJsonPath('order.coffee_loyalty.after.stamps_until_reward', 0)
            ->assertJsonPath('order.coffee_loyalty.after.reward_available', true);

        $orderId = $response->json('order.id');

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.paid_stamps', 9)
            ->assertJsonPath('coffee_loyalty.reward_available', true);

        $this->getJson("/api/establishments/{$establishment->id}/menu")
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.paid_stamps', 9)
            ->assertJsonPath('coffee_loyalty.reward_available', true);

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.stamps_earned', 2)
            ->assertJsonPath('coffee_loyalty.before.paid_stamps', 7)
            ->assertJsonPath('coffee_loyalty.after.paid_stamps', 9);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $cappuccino->id,
            'counts_toward_coffee_card' => true,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'product_id' => $croissant->id,
            'counts_toward_coffee_card' => false,
        ]);
    }

    public function test_non_eligible_products_do_not_award_stamps(): void
    {
        [$establishment, , $croissant] = $this->seedCoffeeVenue();
        $user = User::factory()->create();

        CoffeeLoyaltyCard::query()->create([
            'user_id' => $user->id,
            'paid_stamps' => 3,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $croissant->id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/checkout")
            ->assertCreated()
            ->assertJsonPath('order.coffee_loyalty', null);

        $this->assertDatabaseHas('coffee_loyalty_cards', [
            'user_id' => $user->id,
            'paid_stamps' => 3,
        ]);

        $this->assertDatabaseCount('coffee_loyalty_events', 0);
    }

    public function test_award_is_idempotent_for_repeated_mark_paid(): void
    {
        [$establishment, $cappuccino] = $this->seedCoffeeVenue();
        $user = User::factory()->create();
        $order = $this->makePendingEligibleOrder($user, $establishment, $cappuccino, quantity: 1, paidStampsBefore: 1);

        $service = app(TipTopPayService::class);
        $service->markPaid($order);
        $service->markPaid($order->fresh());

        $this->assertSame(1, CoffeeLoyaltyEvent::query()->where('type', CoffeeLoyaltyEvent::TYPE_EARN)->count());
        $this->assertDatabaseHas('coffee_loyalty_cards', [
            'user_id' => $user->id,
            'paid_stamps' => 2,
        ]);
    }

    public function test_full_refund_reverses_stamps_once(): void
    {
        [$establishment, $cappuccino] = $this->seedCoffeeVenue();
        $user = User::factory()->create();

        CoffeeLoyaltyCard::query()->create([
            'user_id' => $user->id,
            'paid_stamps' => 7,
        ]);

        Sanctum::actingAs($user);

        $size = ProductSize::factory()->create([
            'product_id' => $cappuccino->id,
            'code' => 'M',
            'label' => '400 мл',
            'price' => 1900,
            'is_default' => true,
        ]);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $cappuccino->id,
            'size_id' => $size->id,
            'quantity' => 2,
        ])->assertOk();

        $orderId = $this->postJson("/api/establishments/{$establishment->id}/cart/checkout")
            ->assertCreated()
            ->json('order.id');

        $order = Order::query()->findOrFail($orderId);
        $loyalty = app(CoffeeLoyaltyService::class);

        $loyalty->reverseForRefundedOrder($order);
        $loyalty->reverseForRefundedOrder($order->fresh());

        $this->assertSame(1, CoffeeLoyaltyEvent::query()->where('type', CoffeeLoyaltyEvent::TYPE_REVERSE)->count());
        $this->assertDatabaseHas('coffee_loyalty_cards', [
            'user_id' => $user->id,
            'paid_stamps' => 7,
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.paid_stamps', 7)
            ->assertJsonPath('coffee_loyalty.reward_available', false);

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.stamps_earned', 2)
            ->assertJsonPath('coffee_loyalty.before.paid_stamps', 7)
            ->assertJsonPath('coffee_loyalty.after.paid_stamps', 9);
    }

    public function test_previous_order_keeps_original_loyalty_snapshot_after_new_purchase(): void
    {
        [$establishment, $cappuccino] = $this->seedCoffeeVenue();
        $user = User::factory()->create();
        $size = ProductSize::factory()->create([
            'product_id' => $cappuccino->id,
            'code' => 'M',
            'label' => '400 мл',
            'price' => 1900,
            'is_default' => true,
        ]);

        CoffeeLoyaltyCard::query()->create([
            'user_id' => $user->id,
            'paid_stamps' => 5,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $cappuccino->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ])->assertOk();

        $firstOrderId = $this->postJson("/api/establishments/{$establishment->id}/cart/checkout")
            ->assertCreated()
            ->json('order.id');

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $cappuccino->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/checkout")->assertCreated();

        $this->getJson("/api/orders/{$firstOrderId}")
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.stamps_earned', 1)
            ->assertJsonPath('coffee_loyalty.before.paid_stamps', 5)
            ->assertJsonPath('coffee_loyalty.after.paid_stamps', 6);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.paid_stamps', 7);
    }

    /**
     * @return array{0: Establishment, 1: Product, 2: Product}
     */
    private function seedCoffeeVenue(): array
    {
        $city = City::factory()->create();
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::Coffee,
            'name' => 'Haragun Express',
        ]);
        $establishment = Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'type' => EstablishmentType::Coffee,
            'name' => 'Haragun Express',
        ]);

        $coffee = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Кофе',
            'slug' => 'coffee',
        ]);

        $snacks = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Закуски',
            'slug' => 'snacks',
        ]);

        $cappuccino = Product::factory()->create([
            'establishment_id' => null,
            'brand_id' => $brand->id,
            'menu_category_id' => $coffee->id,
            'kind' => ProductKind::Food,
            'name' => 'Капучино',
            'price' => 1900,
            'counts_toward_coffee_card' => true,
            'is_popular' => true,
        ]);

        $croissant = Product::factory()->create([
            'establishment_id' => null,
            'brand_id' => $brand->id,
            'menu_category_id' => $snacks->id,
            'kind' => ProductKind::Food,
            'name' => 'Круассан',
            'price' => 1200,
            'counts_toward_coffee_card' => false,
            'is_popular' => true,
        ]);

        return [$establishment, $cappuccino, $croissant];
    }

    private function makePendingEligibleOrder(
        User $user,
        Establishment $establishment,
        Product $product,
        int $quantity,
        int $paidStampsBefore,
    ): Order {
        CoffeeLoyaltyCard::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['paid_stamps' => $paidStampsBefore],
        );

        $order = Order::query()->create([
            'user_id' => $user->id,
            'establishment_id' => $establishment->id,
            'type' => 'venue',
            'status' => OrderStatus::Accepted,
            'number' => 9001,
            'guest_number' => $user->guestNumber(),
            'code' => 'HRG-9001',
            'subtotal' => $product->price * $quantity,
            'discount_total' => 0,
            'total' => $product->price * $quantity,
            'items_count' => $quantity,
            'summary_title' => $product->name,
            'summary_short' => "{$quantity} товара",
            'payment_label' => null,
            'payment_status' => PaymentStatus::Pending,
            'payment_provider' => 'tiptop',
            'points_to_earn' => 0,
            'prep_minutes' => 15,
            'paid_at' => null,
            'accepted_at' => null,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'kind' => ProductKind::Food->value,
            'name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'amount' => $product->price * $quantity,
            'is_discount' => false,
            'counts_toward_coffee_card' => true,
            'sort_order' => 1,
        ]);

        return $order->load(['establishment', 'items']);
    }
}
