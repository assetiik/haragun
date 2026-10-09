<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\OrderStatus;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_cart(): void
    {
        $establishment = Establishment::factory()->create([
            'type' => EstablishmentType::Coffee,
        ]);

        $this->getJson("/api/establishments/{$establishment->id}/cart")
            ->assertUnauthorized();
    }

    public function test_user_can_add_items_update_quantity_and_view_checkout_screen(): void
    {
        [$user, $establishment, $latte, $size, $caramel] = $this->seedCoffeeCart();

        Sanctum::actingAs($user);

        $add = $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'modifier_ids' => [$caramel->id],
            'quantity' => 1,
        ]);

        $add
            ->assertOk()
            ->assertJsonPath('establishment.address', 'ул. Сейфуллина 154')
            ->assertJsonPath('items.0.name', 'Латте')
            ->assertJsonPath('items.0.size.label', '400 мл')
            ->assertJsonPath('items.0.modifiers.0.name', 'Карамель')
            ->assertJsonPath('items.0.unit_price', 2940)
            ->assertJsonPath('payment.selected_id', 'kaspi')
            ->assertJsonPath('payment.methods.0.id', 'kaspi')
            ->assertJsonPath('totals.total', 2940)
            ->assertJsonPath('totals.items_count', 1);

        $itemId = $add->json('items.0.id');

        $this->patchJson("/api/establishments/{$establishment->id}/cart/items/{$itemId}", [
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('totals.total', 5880)
            ->assertJsonPath('totals.items_count', 2);

        $this->getJson("/api/establishments/{$establishment->id}/menu")
            ->assertOk()
            ->assertJsonPath('cart.items_count', 2)
            ->assertJsonPath('cart.total', 5880)
            ->assertJsonPath('active_order', null);
    }

    public function test_user_can_clear_cart_after_confirm(): void
    {
        [$user, $establishment, $latte, $size] = $this->seedCoffeeCart();

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
        ])->assertOk();

        $this->deleteJson("/api/establishments/{$establishment->id}/cart")
            ->assertOk()
            ->assertJsonPath('is_empty', true)
            ->assertJsonPath('items', [])
            ->assertJsonPath('totals.total', 0);
    }

    public function test_checkout_creates_accepted_order_and_clears_cart(): void
    {
        [$user, $establishment, $latte, $size, $caramel] = $this->seedCoffeeCart();

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'modifier_ids' => [$caramel->id],
            'quantity' => 1,
        ])->assertOk();

        $response = $this->postJson("/api/establishments/{$establishment->id}/cart/checkout");

        $response
            ->assertCreated()
            ->assertJsonPath('order.status', 'accepted')
            ->assertJsonPath('order.type', 'venue')
            ->assertJsonPath('order.total', 2940)
            ->assertJsonPath('cart.is_empty', true);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'establishment_id' => $establishment->id,
            'status' => OrderStatus::Accepted->value,
            'total' => 2940,
            'payment_label' => 'Kaspi',
        ]);

        $orderNumber = $response->json('order.number');

        $this->getJson("/api/establishments/{$establishment->id}/menu")
            ->assertOk()
            ->assertJsonPath('cart.items_count', 0)
            ->assertJsonPath('active_order.number', $orderNumber)
            ->assertJsonPath('active_order.status', 'accepted')
            ->assertJsonPath('active_order.code', $response->json('order.code'));
    }

    public function test_identical_items_merge_quantity(): void
    {
        [$user, $establishment, $latte, $size] = $this->seedCoffeeCart();

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 2);

        $this->assertCount(1, $this->getJson("/api/establishments/{$establishment->id}/cart")->json('items'));
    }

    public function test_user_can_quote_and_add_foodcourt_snack_without_size(): void
    {
        $city = City::factory()->create();
        $user = User::factory()->create();
        $arena = Establishment::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::IceArena,
            'name' => 'Ледовая арена Haragun',
            'address' => 'пр. Достык, 240',
        ]);
        $foodcourt = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'type' => EstablishmentType::IceArena,
            'slug' => 'foodcourt',
            'name' => 'Фудкорт',
        ]);
        $donut = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Пончик',
            'price' => 800,
            'food_section' => 'Перекус',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$arena->id}/menu/quote", [
            'product_id' => $donut->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('product_id', $donut->id)
            ->assertJsonPath('size', null)
            ->assertJsonPath('modifiers', [])
            ->assertJsonPath('quantity', 2)
            ->assertJsonPath('unit_price', 800)
            ->assertJsonPath('total', 1600);

        $this->postJson("/api/establishments/{$arena->id}/cart/items", [
            'product_id' => $donut->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('items.0.name', 'Пончик')
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('totals.total', 1600);
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product, 3: ProductSize, 4: Modifier}
     */
    private function seedCoffeeCart(): array
    {
        $city = City::factory()->create();
        $user = User::factory()->create();
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::Coffee,
            'name' => 'Кофейня «Москва»',
        ]);
        $establishment = Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'name' => 'Кофейня «Москва»',
            'address' => 'ул. Сейфуллина 154',
            'type' => EstablishmentType::Coffee,
        ]);

        $coffee = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Кофе',
            'slug' => 'coffee',
        ]);

        $latte = Product::factory()->create([
            'establishment_id' => null,
            'brand_id' => $brand->id,
            'menu_category_id' => $coffee->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте',
            'price' => 1690,
            'meta' => '350 мл',
            'description' => 'эспрессо и молоко, мягкая пенка',
            'image_url' => 'https://example.com/latte.jpg',
            'is_popular' => true,
            'is_featured' => true,
        ]);

        $size = ProductSize::factory()->create([
            'product_id' => $latte->id,
            'code' => 'M',
            'label' => '400 мл',
            'volume_ml' => 400,
            'price' => 1690,
            'is_default' => true,
        ]);

        $caramel = Modifier::factory()->create([
            'name' => 'Карамель',
            'price' => 1250,
        ]);

        $latte->modifiers()->attach($caramel->id, ['sort_order' => 1]);

        return [$user, $establishment, $latte, $size, $caramel];
    }
}
