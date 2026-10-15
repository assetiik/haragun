<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ProductKind;
use App\Models\City;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_returns_accepted_coffee_screen(): void
    {
        [$user, $establishment, $latte, $size] = $this->seedCoffee();

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'quantity' => 2,
        ])->assertOk();

        $response = $this->postJson("/api/establishments/{$establishment->id}/cart/checkout");

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', 'accepted')
            ->assertJsonPath('coffee_screen.screen', 'accepted')
            ->assertJsonPath('coffee_screen.guest_number', $user->guestNumber())
            ->assertJsonPath('coffee_screen.primary_action', 'dismiss')
            ->assertJsonPath('coffee_screen.items.0.name', 'Латте')
            ->assertJsonPath('coffee_screen.items.0.image_url', 'https://example.com/latte.jpg')
            ->assertJsonPath('order.guest_number', $user->guestNumber());

        $this->assertCount(2, $response->json('coffee_screen.items'));
        $this->assertSame($response->json('order.number'), $response->json('coffee_screen.pickup_number'));
    }

    public function test_ready_screen_allows_optional_comment_feedback(): void
    {
        [$user, $establishment] = $this->seedCoffee();
        $order = $this->makeVenueOrder($user, $establishment, OrderStatus::Ready);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}/coffee-screen")
            ->assertOk()
            ->assertJsonPath('coffee_screen.screen', 'ready')
            ->assertJsonPath('coffee_screen.feedback.enabled', true)
            ->assertJsonPath('coffee_screen.feedback.comment_required', false)
            ->assertJsonPath('coffee_screen.feedback.ratings.0', 'positive')
            ->assertJsonPath('coffee_screen.feedback.ratings.1', 'negative')
            ->assertJsonPath('coffee_screen.primary_action', 'close');

        $this->postJson("/api/orders/{$order->id}/feedback", [
            'rating' => 'positive',
            'likes' => ['taste', 'price'],
            'dislikes' => [],
        ])
            ->assertCreated()
            ->assertJsonPath('feedback.rating', 'positive')
            ->assertJsonPath('feedback.likes.0', 'taste')
            ->assertJsonPath('coffee_screen.screen', 'rated')
            ->assertJsonPath('feedback.comment', null);

        $this->assertDatabaseHas('order_feedback', [
            'order_id' => $order->id,
            'rating' => 'positive',
            'comment' => null,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Completed->value,
        ]);
    }

    public function test_feedback_rejects_invalid_tags_and_duplicate(): void
    {
        [$user, $establishment] = $this->seedCoffee();
        $order = $this->makeVenueOrder($user, $establishment, OrderStatus::Ready);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/feedback", [
            'rating' => 'negative',
            'likes' => ['underheated'],
        ])->assertUnprocessable();

        $this->postJson("/api/orders/{$order->id}/feedback", [
            'rating' => 'negative',
            'dislikes' => ['underheated', 'overheated'],
            'comment' => 'Можно теплее',
        ])->assertCreated();

        $this->postJson("/api/orders/{$order->id}/feedback", [
            'rating' => 'positive',
        ])->assertUnprocessable();
    }

    public function test_cannot_rate_before_ready(): void
    {
        [$user, $establishment] = $this->seedCoffee();
        $order = $this->makeVenueOrder($user, $establishment, OrderStatus::Accepted);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/feedback", [
            'rating' => 'positive',
        ])->assertUnprocessable();
    }

    public function test_like_button_can_submit_rating_only(): void
    {
        [$user, $establishment] = $this->seedCoffee();
        $order = $this->makeVenueOrder($user, $establishment, OrderStatus::Ready);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/feedback", [
            'rating' => 'positive',
        ])
            ->assertCreated()
            ->assertJsonPath('feedback.rating', 'positive')
            ->assertJsonPath('coffee_screen.screen', 'rated')
            ->assertJsonPath('coffee_screen.feedback.enabled', false);

        $this->assertDatabaseHas('order_feedback', [
            'order_id' => $order->id,
            'rating' => 'positive',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::Completed->value,
        ]);
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product, 3: ProductSize}
     */
    private function seedCoffee(): array
    {
        $city = City::factory()->create();
        $user = User::factory()->create();
        $establishment = Establishment::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::Coffee,
            'address' => 'ул. Сейфуллина 154',
        ]);

        $category = MenuCategory::factory()->create([
            'establishment_id' => $establishment->id,
            'slug' => 'coffee',
            'name' => 'Кофе',
        ]);

        $latte = Product::factory()->create([
            'establishment_id' => $establishment->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте',
            'price' => 1690,
            'image_url' => 'https://example.com/latte.jpg',
        ]);

        $size = ProductSize::factory()->create([
            'product_id' => $latte->id,
            'code' => 'M',
            'label' => '400 мл',
            'price' => 1690,
            'is_default' => true,
        ]);

        $caramel = Modifier::factory()->create([
            'name' => 'Карамель',
            'price' => 1250,
        ]);
        $latte->modifiers()->attach($caramel->id, ['sort_order' => 1]);

        return [$user, $establishment, $latte, $size];
    }

    private function makeVenueOrder(User $user, Establishment $establishment, OrderStatus $status): Order
    {
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $establishment->id,
            'type' => OrderType::Venue,
            'status' => $status,
            'number' => 356,
            'guest_number' => $user->guestNumber(),
            'code' => 'HRG-356',
            'ready_at' => $status === OrderStatus::Ready ? now() : null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Карамельный Латте',
            'quantity' => 2,
            'image_url' => 'https://example.com/caramel.jpg',
        ]);

        return $order;
    }
}
