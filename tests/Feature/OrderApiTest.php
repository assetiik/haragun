<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ProductKind;
use App\Models\City;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SkateSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_ticket_order_from_checkout_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 12:00:00'));

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
            ->assertJsonPath('order.status', 'confirmed')
            ->assertJsonPath('order.total', 10000)
            ->assertJsonPath('order.pickup.label', 'Код брони');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'establishment_id' => $arena->id,
            'type' => OrderType::Tickets->value,
            'total' => 10000,
        ]);
    }

    public function test_orders_index_splits_active_and_history_for_venue_tab(): void
    {
        $user = User::factory()->create();
        $coffee = Establishment::factory()->create([
            'name' => 'Кофейня Haragun',
            'type' => EstablishmentType::Coffee,
            'address' => 'ул. Кунаева, 12',
        ]);

        $active = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $coffee->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Preparing,
            'number' => 4821,
            'code' => 'HRG-4821',
            'total' => 17550,
            'summary_short' => '4 напитка, 4 десерта и сэндвич',
            'preparing_at' => now()->subMinutes(1),
            'prep_minutes' => 5,
        ]);

        OrderItem::factory()->create([
            'order_id' => $active->id,
            'name' => 'Капучино',
            'quantity' => 2,
            'amount' => 2400,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $coffee->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Completed,
            'number' => 3902,
            'code' => 'HRG-3902',
            'total' => 2250,
            'summary_short' => 'Латте, круассан',
            'completed_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/orders?type=venue')
            ->assertOk()
            ->assertJsonPath('selected_tab', 'venue')
            ->assertJsonPath('tabs.0.badge', 1)
            ->assertJsonPath('sections.0.key', 'active')
            ->assertJsonPath('sections.0.orders.0.number', 4821)
            ->assertJsonPath('sections.0.orders.0.primary_action.code', 'HRG-4821')
            ->assertJsonPath('sections.1.key', 'history')
            ->assertJsonPath('sections.1.orders.0.number', 3902);
    }

    public function test_user_can_view_own_order_details(): void
    {
        $user = User::factory()->create();
        $coffee = Establishment::factory()->create([
            'name' => 'Кофейня Haragun',
            'address' => 'ул. Кунаева, 12',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $coffee->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Preparing,
            'number' => 4821,
            'code' => 'HRG-4821',
            'total' => 8100,
            'summary_title' => 'Капучино, чизкейк и сэндвич',
            'points_to_earn' => 405,
            'preparing_at' => now()->subMinute(),
            'prep_minutes' => 5,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Капучино',
            'subtitle' => '250 мл · без сахара',
            'quantity' => 2,
            'amount' => 2400,
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('title', 'Заказ №4821')
            ->assertJsonPath('code', 'HRG-4821')
            ->assertJsonPath('summary_title', 'Капучино, чизкейк и сэндвич')
            ->assertJsonPath('items.0.name', 'Капучино')
            ->assertJsonPath('pickup.code', 'HRG-4821')
            ->assertJsonPath('points_note', 'Начислим 405 баллов после выдачи');
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $order = Order::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/orders/{$order->id}")->assertNotFound();
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product, 3: Product, 4: Product}
     */
    private function seedArena(): array
    {
        $user = User::factory()->create();
        $city = City::factory()->create();
        $arena = Establishment::factory()->create([
            'city_id' => $city->id,
            'name' => 'Ледовая арена Haragun',
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

        foreach ([38, 41] as $size) {
            SkateSize::query()->create([
                'product_id' => $skates->id,
                'size' => $size,
                'is_available' => true,
            ]);
        }

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
