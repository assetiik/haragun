<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\MenuCatalog;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Modifier;
use App\Models\ModifierCategory;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoffeeMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_returns_categories_featured_and_popular_products(): void
    {
        [$establishment, $latte] = $this->seedCoffeeMenu();

        $response = $this->getJson("/api/establishments/{$establishment->id}/menu");

        $response
            ->assertOk()
            ->assertJsonPath('header.address', 'ул. Сейфуллина 154')
            ->assertJsonPath('selected_category', 'popular')
            ->assertJsonPath('categories.0.slug', 'popular')
            ->assertJsonPath('categories.1.slug', 'tea')
            ->assertJsonPath('categories.2.slug', 'coffee')
            ->assertJsonPath('categories.3.slug', 'lemonade')
            ->assertJsonPath('featured.id', $latte->id)
            ->assertJsonPath('featured.name', 'Латте')
            ->assertJsonPath('featured.price', 1690)
            ->assertJsonPath('products.0.name', 'Латте')
            ->assertJsonPath('cart.items_count', 0)
            ->assertJsonPath('cart.total', 0);

        $this->assertCount(3, $response->json('products'));
    }

    public function test_menu_returns_a_public_url_for_uploaded_photos(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/latte.jpg', 'image');

        [$establishment, $latte] = $this->seedCoffeeMenu();
        $latte->update(['image_url' => 'products/latte.jpg']);

        $this->getJson("/api/establishments/{$establishment->id}/menu")
            ->assertOk()
            ->assertJsonPath('featured.image_url', Storage::disk('public')->url('products/latte.jpg'));
    }

    public function test_menu_filters_by_coffee_category(): void
    {
        [$establishment] = $this->seedCoffeeMenu();

        $this->getJson("/api/establishments/{$establishment->id}/menu?category=coffee")
            ->assertOk()
            ->assertJsonPath('selected_category', 'coffee')
            ->assertJsonPath('products.0.name', 'Латте')
            ->assertJsonPath('products.1.name', 'Капучино');

        $this->assertCount(2, $this->getJson("/api/establishments/{$establishment->id}/menu?category=coffee")->json('products'));
    }

    public function test_product_detail_includes_sizes_and_modifier_groups(): void
    {
        [$establishment, $latte, $caramel] = $this->seedCoffeeMenu();

        $response = $this->getJson("/api/establishments/{$establishment->id}/menu/products/{$latte->id}");

        $response
            ->assertOk()
            ->assertJsonPath('name', 'Латте')
            ->assertJsonPath('description', 'эспрессо и молоко, мягкая пенка')
            ->assertJsonPath('background_color', '#C8A45D')
            ->assertJsonPath('sizes.1.code', 'M')
            ->assertJsonPath('sizes.1.is_default', true)
            ->assertJsonPath('selection_mode', 'coffee')
            ->assertJsonPath('kind', 'food')
            ->assertJsonPath('modifiers.0.id', $caramel->id)
            ->assertJsonPath('modifiers.0.name', 'Карамель')
            ->assertJsonPath('modifiers.0.price', 1250)
            ->assertJsonPath('modifier_groups.0.name', 'Топпинги')
            ->assertJsonPath('modifier_groups.0.slug', 'toppings')
            ->assertJsonPath('modifier_groups.0.modifiers.0.id', $caramel->id)
            ->assertJsonPath('modifier_groups.0.modifiers.0.price', 1250)
            ->assertJsonPath('modifier_groups.1.name', 'Молоко')
            ->assertJsonPath('modifier_groups.1.slug', 'milk')
            ->assertJsonPath('modifier_groups.2.name', 'Сиропы')
            ->assertJsonPath('modifier_groups.2.slug', 'syrups');
    }

    public function test_quote_rejects_a_modifier_that_is_not_on_the_product(): void
    {
        [$establishment, $latte] = $this->seedCoffeeMenu();
        $other = Modifier::factory()->create([
            'name' => 'Чужой сироп',
            'price' => 100,
        ]);

        $this->postJson("/api/establishments/{$establishment->id}/menu/quote", [
            'product_id' => $latte->id,
            'modifier_ids' => [$other->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('modifier_ids')
            ->assertJsonPath('errors.modifier_ids.0', 'Добавка Чужой сироп не подходит к этому напитку.');
    }

    public function test_quote_item_sums_size_modifiers_and_quantity(): void
    {
        [$establishment, $latte, $caramel, $large] = $this->seedCoffeeMenu();

        $this->postJson("/api/establishments/{$establishment->id}/menu/quote", [
            'product_id' => $latte->id,
            'size_id' => $large->id,
            'modifier_ids' => [$caramel->id],
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('unit_price', 3140)
            ->assertJsonPath('total', 6280)
            ->assertJsonPath('quantity', 2)
            ->assertJsonPath('modifiers.0.name', 'Карамель');
    }

    public function test_ice_arena_menu_returns_coffee_drinks(): void
    {
        [$arena, $latte] = $this->seedCoffeeMenu(EstablishmentType::IceArena);
        MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::IceArena,
            'catalog' => MenuCatalog::Drinks,
            'name' => 'Кофе',
            'slug' => 'coffee',
            'sort_order' => 2,
        ]);

        $this->getJson("/api/establishments/{$arena->id}/menu")
            ->assertOk()
            ->assertJsonPath('featured.id', $latte->id)
            ->assertJsonPath('featured.name', 'Латте')
            ->assertJsonPath('categories.1.slug', 'coffee');
    }

    public function test_ice_arena_menu_uses_only_haragun_drink_categories(): void
    {
        [$arena] = $this->seedCoffeeMenu(EstablishmentType::IceArena);
        MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::IceArena,
            'catalog' => MenuCatalog::Drinks,
            'name' => 'Фреш',
            'slug' => 'fresh',
        ]);

        $arenaSlugs = collect($this->getJson("/api/establishments/{$arena->id}/menu")->json('categories'))->pluck('slug');
        $this->assertTrue($arenaSlugs->contains('fresh'));
        $this->assertFalse($arenaSlugs->contains('tea'));
        $this->assertFalse($arenaSlugs->contains('coffee'));
        $this->assertFalse($arenaSlugs->contains('lemonade'));

        $city = City::factory()->create();
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::Coffee,
        ]);
        $shop = Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'type' => EstablishmentType::Coffee,
        ]);

        $shopSlugs = collect($this->getJson("/api/establishments/{$shop->id}/menu")->json('categories'))->pluck('slug');
        $this->assertFalse($shopSlugs->contains('fresh'));
        $this->assertTrue($shopSlugs->contains('tea'));
    }

    public function test_ice_arena_menu_hides_foodcourt_snacks(): void
    {
        [$arena] = $this->seedCoffeeMenu(EstablishmentType::IceArena);
        $foodcourt = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'type' => EstablishmentType::IceArena,
            'slug' => 'foodcourt',
            'name' => 'Фудкорт',
        ]);
        Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Хот-дог',
            'is_popular' => true,
            'is_featured' => true,
        ]);

        $response = $this->getJson("/api/establishments/{$arena->id}/menu");

        $response->assertOk();
        $this->assertSame('Латте', $response->json('featured.name'));
        $this->assertNotContains('Хот-дог', collect($response->json('products'))->pluck('name')->all());
    }

    public function test_ice_arena_foodcourt_product_show_returns_quantity_mode(): void
    {
        [$arena] = $this->seedCoffeeMenu(EstablishmentType::IceArena);
        $foodcourt = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'type' => EstablishmentType::IceArena,
            'slug' => 'foodcourt',
            'name' => 'Фудкорт',
        ]);
        $hotDog = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Хот-дог',
            'price' => 1500,
            'description' => 'с кетчупом',
            'food_section' => 'Перекус',
        ]);

        $this->getJson("/api/establishments/{$arena->id}/menu/products/{$hotDog->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Хот-дог')
            ->assertJsonPath('kind', 'food')
            ->assertJsonPath('selection_mode', 'quantity')
            ->assertJsonPath('base_price', 1500)
            ->assertJsonPath('sizes', [])
            ->assertJsonPath('modifiers', [])
            ->assertJsonPath('modifier_groups', []);

        $this->getJson("/api/establishments/{$arena->id}/foodcourt/products/{$hotDog->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Хот-дог')
            ->assertJsonPath('selection_mode', 'quantity')
            ->assertJsonPath('base_price', 1500);
    }

    public function test_inactive_ice_arena_menu_returns_404(): void
    {
        $city = City::factory()->create();
        $arena = Establishment::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::IceArena,
            'is_active' => false,
        ]);

        $this->getJson("/api/establishments/{$arena->id}/menu")
            ->assertNotFound();
    }

    /**
     * @return array{0: Establishment, 1: Product, 2: Modifier, 3: ProductSize}
     */
    private function seedCoffeeMenu(EstablishmentType $type = EstablishmentType::Coffee): array
    {
        $city = City::factory()->create();
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => $type,
            'name' => $type === EstablishmentType::Coffee ? 'Кофейня «Москва»' : 'Ледовая арена Haragun',
        ]);

        $establishment = Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'name' => $brand->name,
            'address' => 'ул. Сейфуллина 154',
            'type' => $type,
        ]);

        $tea = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Чай',
            'slug' => 'tea',
            'sort_order' => 1,
        ]);
        $coffee = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Кофе',
            'slug' => 'coffee',
            'sort_order' => 2,
        ]);
        MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Лимонад',
            'slug' => 'lemonade',
            'sort_order' => 3,
        ]);

        $owner = ['brand_id' => $brand->id, 'establishment_id' => null];

        $latte = Product::factory()->create([
            ...$owner,
            'menu_category_id' => $coffee->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте',
            'price' => 1690,
            'meta' => '350 мл',
            'description' => 'эспрессо и молоко, мягкая пенка',
            'image_url' => 'https://example.com/latte.jpg',
            'background_color' => '#C8A45D',
            'is_popular' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        Product::factory()->create([
            ...$owner,
            'menu_category_id' => $coffee->id,
            'kind' => ProductKind::Food,
            'name' => 'Капучино',
            'price' => 1590,
            'is_popular' => true,
            'is_featured' => false,
            'sort_order' => 2,
        ]);

        Product::factory()->create([
            ...$owner,
            'menu_category_id' => $tea->id,
            'kind' => ProductKind::Food,
            'name' => 'Чай зелёный',
            'price' => 890,
            'is_popular' => true,
            'sort_order' => 1,
        ]);

        ProductSize::factory()->create([
            'product_id' => $latte->id,
            'code' => 'S',
            'label' => '300 мл',
            'volume_ml' => 300,
            'price' => 1490,
            'is_default' => false,
            'sort_order' => 1,
        ]);
        ProductSize::factory()->create([
            'product_id' => $latte->id,
            'code' => 'M',
            'label' => '400 мл',
            'volume_ml' => 400,
            'price' => 1690,
            'is_default' => true,
            'sort_order' => 2,
        ]);
        $large = ProductSize::factory()->create([
            'product_id' => $latte->id,
            'code' => 'L',
            'label' => '500 мл',
            'volume_ml' => 500,
            'price' => 1890,
            'is_default' => false,
            'sort_order' => 3,
        ]);

        $toppings = ModifierCategory::factory()->create([
            'name' => 'Топпинги',
            'slug' => 'toppings',
            'sort_order' => 1,
        ]);
        $milk = ModifierCategory::factory()->create([
            'name' => 'Молоко',
            'slug' => 'milk',
            'sort_order' => 2,
        ]);
        $syrups = ModifierCategory::factory()->create([
            'name' => 'Сиропы',
            'slug' => 'syrups',
            'sort_order' => 3,
        ]);

        $caramel = Modifier::factory()->create([
            'modifier_category_id' => $toppings->id,
            'name' => 'Карамель',
            'price' => 1250,
            'sort_order' => 1,
        ]);
        $oatMilk = Modifier::factory()->create([
            'modifier_category_id' => $milk->id,
            'name' => 'Овсяное',
            'price' => 350,
            'sort_order' => 2,
        ]);
        $vanilla = Modifier::factory()->create([
            'modifier_category_id' => $syrups->id,
            'name' => 'Ваниль',
            'price' => 250,
            'sort_order' => 3,
        ]);

        $latte->modifiers()->sync([
            $caramel->id => ['sort_order' => 1],
            $oatMilk->id => ['sort_order' => 2],
            $vanilla->id => ['sort_order' => 3],
        ]);

        return [$establishment, $latte, $caramel, $large];
    }
}
