<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use App\Models\WelcomeCoffeeGift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WelcomeCoffeeGiftApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_registration_grants_welcome_espresso_gift(): void
    {
        $this->postJson('/api/auth/send-code', [
            'phone' => '77784046889',
        ])->assertOk();

        $this->postJson('/api/auth/verify-code', [
            'phone' => '77784046889',
            'code' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('welcome_gift.available', true)
            ->assertJsonPath('welcome_gift.title', '1 эспрессо в подарок');

        $this->assertDatabaseHas('welcome_coffee_gifts', [
            'phone' => '77784046889',
            'status' => WelcomeCoffeeGift::STATUS_AVAILABLE,
        ]);
    }

    public function test_deleted_account_does_not_get_gift_again(): void
    {
        $this->postJson('/api/auth/send-code', ['phone' => '77071234567'])->assertOk();
        $token = $this->postJson('/api/auth/verify-code', [
            'phone' => '77071234567',
            'code' => '1234',
        ])->json('token');

        $this->withToken($token)
            ->deleteJson('/api/auth/account')
            ->assertNoContent();

        $this->assertDatabaseHas('welcome_coffee_gifts', [
            'phone' => '77071234567',
            'user_id' => null,
        ]);

        $this->postJson('/api/auth/send-code', ['phone' => '77071234567'])->assertOk();
        $this->postJson('/api/auth/verify-code', [
            'phone' => '77071234567',
            'code' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('welcome_gift.available', false);

        $this->assertSame(1, WelcomeCoffeeGift::query()->where('phone', '77071234567')->count());
        $this->assertDatabaseHas('welcome_coffee_gifts', [
            'phone' => '77071234567',
            'status' => WelcomeCoffeeGift::STATUS_REDEEMED,
        ]);
    }

    public function test_guest_home_returns_null_welcome_gift_and_coffee_menu_hides_on_arena(): void
    {
        $city = City::factory()->create();
        $arena = Establishment::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::IceArena,
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('welcome_gift', null);

        $this->getJson("/api/establishments/{$arena->id}/menu")
            ->assertOk()
            ->assertJsonPath('welcome_gift', null);
    }

    public function test_checkout_applies_one_free_espresso_with_other_paid_drinks(): void
    {
        [$user, $establishment, $espresso, $latte, $espressoSize, $latteSize] = $this->seedCoffeeWithGift();

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $latteSize->id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $espresso->id,
            'size_id' => $espressoSize->id,
            'quantity' => 1,
        ])->assertOk();

        $this->patchJson("/api/establishments/{$establishment->id}/cart", [
            'apply_welcome_gift' => true,
        ])
            ->assertOk()
            ->assertJsonPath('welcome_gift.applied', true)
            ->assertJsonPath('totals.welcome_gift_discount', 990)
            ->assertJsonPath('totals.total', 3380);

        $response = $this->postJson("/api/establishments/{$establishment->id}/cart/checkout");

        $response
            ->assertCreated()
            ->assertJsonPath('order.welcome_gift_applied', true)
            ->assertJsonPath('order.total', 3380)
            ->assertJsonPath('order.items_count', 3);

        $items = collect($response->json('order.items'));
        $this->assertTrue($items->contains(
            fn (array $item): bool => ($item['is_welcome_gift'] ?? false) === true
                && $item['amount'] === 0
                && $item['product_id'] === $espresso->id
        ));

        $this->assertDatabaseHas('welcome_coffee_gifts', [
            'user_id' => $user->id,
            'status' => WelcomeCoffeeGift::STATUS_REDEEMED,
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('welcome_gift.available', false);
    }

    public function test_zero_total_gift_only_checkout_is_paid_without_tiptop(): void
    {
        config([
            'services.tiptop_pay.enabled' => true,
            'services.tiptop_pay.public_id' => null,
            'services.tiptop_pay.api_secret' => null,
            'services.tiptop_pay.merchants.coffee.public_id' => 'pk_coffee_test',
            'services.tiptop_pay.merchants.coffee.api_secret' => 'coffee_api_secret',
        ]);

        [$user, $establishment, $espresso, , $espressoSize] = $this->seedCoffeeWithGift();

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $espresso->id,
            'size_id' => $espressoSize->id,
            'quantity' => 1,
        ])->assertOk();

        $this->patchJson("/api/establishments/{$establishment->id}/cart", [
            'apply_welcome_gift' => true,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/checkout")
            ->assertCreated()
            ->assertJsonPath('order.total', 0)
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('order.payment_label', 'Подарок')
            ->assertJsonPath('order.welcome_gift_applied', true)
            ->assertJsonPath('payment', null)
            ->assertJsonPath('coffee_screen.screen', 'accepted');

        $this->assertDatabaseHas('welcome_coffee_gifts', [
            'user_id' => $user->id,
            'status' => WelcomeCoffeeGift::STATUS_REDEEMED,
        ]);
    }

    public function test_welcome_gift_does_not_count_toward_coffee_card(): void
    {
        [$user, $establishment, $espresso, , $espressoSize] = $this->seedCoffeeWithGift();
        $espresso->update(['counts_toward_coffee_card' => true]);

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $espresso->id,
            'size_id' => $espressoSize->id,
            'quantity' => 1,
        ])->assertOk();

        $this->patchJson("/api/establishments/{$establishment->id}/cart", [
            'apply_welcome_gift' => true,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/checkout")
            ->assertCreated()
            ->assertJsonPath('order.coffee_loyalty', null)
            ->assertJsonPath('order.welcome_gift_applied', true);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('coffee_loyalty.paid_stamps', 0);
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Product, 3: Product, 4: ProductSize, 5: ProductSize}
     */
    private function seedCoffeeWithGift(): array
    {
        $city = City::factory()->create();
        $user = User::factory()->create(['phone' => '77770001122']);
        WelcomeCoffeeGift::query()->create([
            'phone' => $user->phone,
            'user_id' => $user->id,
            'status' => WelcomeCoffeeGift::STATUS_AVAILABLE,
            'granted_at' => now(),
        ]);

        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::Coffee,
        ]);
        $establishment = Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'type' => EstablishmentType::Coffee,
        ]);
        $category = MenuCategory::factory()->create([
            'type' => EstablishmentType::Coffee,
            'slug' => 'coffee',
        ]);

        $espresso = Product::factory()->create([
            'establishment_id' => null,
            'brand_id' => $brand->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Food,
            'name' => 'Эспрессо',
            'price' => 990,
            'is_welcome_espresso' => true,
            'is_popular' => true,
        ]);
        $latte = Product::factory()->create([
            'establishment_id' => null,
            'brand_id' => $brand->id,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте',
            'price' => 1690,
            'is_welcome_espresso' => false,
            'is_popular' => true,
        ]);

        $espressoSize = ProductSize::factory()->create([
            'product_id' => $espresso->id,
            'code' => 'S',
            'label' => '30 мл',
            'price' => 990,
            'is_default' => true,
        ]);
        $latteSize = ProductSize::factory()->create([
            'product_id' => $latte->id,
            'code' => 'M',
            'label' => '400 мл',
            'price' => 1690,
            'is_default' => true,
        ]);

        return [$user, $establishment, $espresso, $latte, $espressoSize, $latteSize];
    }
}
