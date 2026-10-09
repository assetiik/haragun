<?php

namespace Tests\Feature;

use App\Enums\BannerPlacement;
use App\Enums\EstablishmentType;
use App\Enums\FeedbackRating;
use App\Enums\ProductKind;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Cities\CityResource;
use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\Cities\Pages\EditCity;
use App\Filament\Resources\Establishments\EstablishmentResource;
use App\Filament\Resources\Establishments\Pages\CreateEstablishment;
use App\Filament\Resources\Establishments\Pages\EditEstablishment;
use App\Filament\Resources\Establishments\Pages\ListEstablishments;
use App\Filament\Resources\MenuCategories\Pages\CreateMenuCategory;
use App\Filament\Resources\Modifiers\ModifierResource;
use App\Filament\Resources\Modifiers\Pages\CreateModifier;
use App\Filament\Resources\OrderFeedback\OrderFeedbackResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Users\UserResource;
use App\Models\Admin;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Modifier;
use App\Models\ModifierCategory;
use App\Models\OrderFeedback;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\SkateSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_is_available(): void
    {
        $this->get('/admin/login')
            ->assertOk();
    }

    public function test_admin_can_login_with_admin_credentials(): void
    {
        Admin::factory()->create([
            'email' => 'admin',
            'password' => 'admin',
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'admin',
                'password' => 'admin',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticated('admin');
    }

    public function test_admin_can_open_dashboard(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin',
            'password' => 'admin',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk();
    }

    public function test_guest_is_redirected_from_admin(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_sidebar_separates_haragun_from_cafes(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Арена')
            ->assertSee('Коньки')
            ->assertSee('Точки')
            ->assertSee('tab=haragun', false)
            ->assertSee('tab=haragun-cafe', false)
            ->assertSee('tab=haragun-skates', false)
            ->assertSee('tab=cafes', false)
            ->assertSee('Кофейни')
            ->assertSee('Добавки')
            ->assertDontSee(BrandResource::getUrl().'?tab=haragun', false)
            ->assertDontSee('Размеры')
            ->assertDontSee('Группы добавок')
            ->assertDontSee('Каталог');
    }

    public function test_haragun_establishments_list_hides_coffee_shops(): void
    {
        $admin = Admin::factory()->create();
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
            'name' => 'Haragun Ice Unique',
        ]);
        $cafe = Establishment::factory()->create([
            'type' => EstablishmentType::Coffee,
            'name' => 'Coffee Shop Unique',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ListEstablishments::class)
            ->set('activeTab', 'haragun')
            ->assertCanSeeTableRecords([$arena])
            ->assertCanNotSeeTableRecords([$cafe]);
    }

    public function test_creating_a_city_redirects_to_the_cities_table(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateCity::class)
            ->fillForm([
                'name' => 'Алматы',
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(CityResource::getUrl('index'));

        $this->assertDatabaseHas('cities', [
            'name' => 'Алматы',
        ]);
    }

    public function test_creating_an_establishment_redirects_to_the_table_with_the_venue_tab(): void
    {
        $admin = Admin::factory()->create();
        $city = City::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::withQueryParams(['tab' => 'cafes'])
            ->test(CreateEstablishment::class)
            ->fillForm([
                'name' => 'Coffee New Point',
                'sort_order' => 0,
                'is_active' => true,
                'city_id' => $city->id,
                'address' => 'Abay 1',
                'opens_at' => '10:00',
                'closes_at' => '22:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(EstablishmentResource::getUrl('index').'?tab=cafes');

        $this->assertDatabaseHas('establishments', [
            'name' => 'Coffee New Point',
            'type' => EstablishmentType::Coffee->value,
        ]);
    }

    public function test_editing_a_city_redirects_to_the_cities_table(): void
    {
        $admin = Admin::factory()->create();
        $city = City::factory()->create([
            'name' => 'Алматы',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(EditCity::class, ['record' => $city->getRouteKey()])
            ->fillForm([
                'name' => 'Астана',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(CityResource::getUrl('index'));

        $this->assertDatabaseHas('cities', [
            'id' => $city->id,
            'name' => 'Астана',
        ]);
    }

    public function test_editing_an_establishment_redirects_to_the_table_with_the_venue_tab(): void
    {
        $admin = Admin::factory()->create();
        $establishment = Establishment::factory()->create([
            'type' => EstablishmentType::Coffee,
            'name' => 'Coffee Old Point',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(EditEstablishment::class, ['record' => $establishment->getRouteKey()])
            ->fillForm([
                'name' => 'Coffee Updated Point',
                'latitude' => 43.2567000,
                'longitude' => 76.9286000,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirect(EstablishmentResource::getUrl('index').'?tab=cafes');

        $this->assertDatabaseHas('establishments', [
            'id' => $establishment->id,
            'name' => 'Coffee Updated Point',
            'latitude' => 43.2567000,
            'longitude' => 76.9286000,
        ]);
    }

    public function test_creating_a_coffee_menu_category_skips_establishment_and_booking(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::withQueryParams(['tab' => 'cafes'])
            ->test(CreateMenuCategory::class)
            ->assertFormFieldIsHidden('establishment_id')
            ->assertFormFieldIsHidden('slug')
            ->assertFormFieldIsHidden('requires_booking')
            ->fillForm([
                'name' => 'Чай',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('menu_categories', [
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee->value,
            'name' => 'Чай',
            'slug' => 'tea',
            'requires_booking' => false,
        ]);
    }

    public function test_creating_a_coffee_drink_hides_extra_fields_and_stores_an_uploaded_photo(): void
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();
        $brand = Brand::factory()->create([
            'type' => EstablishmentType::Coffee,
            'name' => 'Кофейня «Арбат»',
        ]);
        $category = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Чай',
            'slug' => 'tea',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin, 'admin');

        $vanilla = Modifier::factory()->create([
            'name' => 'Ваниль',
            'price' => 250,
            'sort_order' => 1,
        ]);

        Livewire::withQueryParams(['tab' => 'cafes'])
            ->test(CreateProduct::class)
            ->assertSee('Чай')
            ->assertFormFieldIsHidden('kind')
            ->assertFormFieldIsHidden('food_section')
            ->assertFormFieldIsHidden('price')
            ->assertFormFieldIsHidden('establishment_id')
            ->assertFormFieldIsVisible('brand_id')
            ->assertFormFieldIsVisible('modifiers')
            ->assertFormFieldIsVisible('sizes')
            ->assertFormFieldDoesNotExist('is_popular')
            ->assertFormFieldDoesNotExist('is_featured')
            ->fillForm([
                'name' => 'Чай зелёный',
                'sort_order' => 1,
                'is_active' => true,
                'brand_id' => $brand->id,
                'menu_category_id' => $category->id,
                'image_url' => UploadedFile::fake()->image('tea.jpg', 120, 120),
                'modifiers' => [$vanilla->id],
                'sizes' => [
                    [
                        'code' => 'S',
                        'volume_ml' => 300,
                        'label' => '300 мл',
                        'price' => 790,
                        'is_default' => false,
                    ],
                    [
                        'code' => 'M',
                        'volume_ml' => 400,
                        'label' => '400 мл',
                        'price' => 890,
                        'is_default' => true,
                    ],
                    [
                        'code' => 'L',
                        'volume_ml' => 500,
                        'label' => '500 мл',
                        'price' => 990,
                        'is_default' => false,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('name', 'Чай зелёный')->first();

        $this->assertNotNull($product);
        $this->assertSame(ProductKind::Food, $product->kind);
        $this->assertNull($product->food_section);
        $this->assertNull($product->establishment_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame(890, $product->price);
        $this->assertSame('400 мл', $product->meta);
        $this->assertNotNull($product->image_url);
        Storage::disk('public')->assertExists($product->image_url);
        $this->assertTrue($product->modifiers->contains($vanilla));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'brand_id' => $brand->id,
            'establishment_id' => null,
            'menu_category_id' => $category->id,
            'price' => 890,
        ]);
        $this->assertDatabaseHas('product_sizes', [
            'product_id' => $product->id,
            'code' => 'M',
            'label' => '400 мл',
            'volume_ml' => 400,
            'price' => 890,
            'is_default' => true,
        ]);
        $this->assertSame(3, ProductSize::query()->where('product_id', $product->id)->count());
    }

    public function test_creating_a_coffee_drink_skips_a_size_without_price(): void
    {
        $admin = Admin::factory()->create();
        $brand = Brand::factory()->create([
            'type' => EstablishmentType::Coffee,
        ]);
        $category = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Чай',
            'slug' => 'tea',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::withQueryParams(['tab' => 'cafes'])
            ->test(CreateProduct::class)
            ->fillForm([
                'name' => 'Эспрессо',
                'sort_order' => 1,
                'is_active' => true,
                'brand_id' => $brand->id,
                'menu_category_id' => $category->id,
                'sizes' => [
                    [
                        'code' => 'S',
                        'volume_ml' => 30,
                        'price' => 990,
                        'is_default' => true,
                    ],
                    [
                        'code' => 'M',
                        'volume_ml' => 60,
                        'price' => 1290,
                        'is_default' => false,
                    ],
                    [
                        'code' => 'L',
                        'volume_ml' => 500,
                        'price' => null,
                        'is_default' => false,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('name', 'Эспрессо')->first();

        $this->assertNotNull($product);
        $this->assertSame(990, $product->price);
        $this->assertSame('30 мл', $product->meta);
        $this->assertSame(2, ProductSize::query()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('product_sizes', [
            'product_id' => $product->id,
            'code' => 'S',
            'price' => 990,
        ]);
        $this->assertDatabaseMissing('product_sizes', [
            'product_id' => $product->id,
            'code' => 'L',
        ]);
    }

    public function test_coffee_drink_create_form_starts_with_three_sizes(): void
    {
        $admin = Admin::factory()->create();
        Establishment::factory()->create([
            'type' => EstablishmentType::Coffee,
        ]);
        MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Чай',
            'slug' => 'tea',
        ]);

        $this->actingAs($admin, 'admin');

        $sizes = collect(
            Livewire::withQueryParams(['tab' => 'cafes'])
                ->test(CreateProduct::class)
                ->instance()
                ->form
                ->getRawState()['sizes'] ?? []
        );

        $this->assertCount(3, $sizes);
        $this->assertSame(['S', 'M', 'L'], $sizes->pluck('code')->values()->all());
        $this->assertSame([300, 400, 500], $sizes->pluck('volume_ml')->values()->all());
        $this->assertTrue((bool) $sizes->firstWhere('code', 'M')['is_default']);
        $this->assertSame('400 мл', $sizes->firstWhere('code', 'M')['label']);
    }

    public function test_creating_a_haragun_cafe_drink_uses_the_arena_and_a_shared_coffee_category(): void
    {
        $admin = Admin::factory()->create();
        $brand = Brand::factory()->create([
            'type' => EstablishmentType::IceArena,
            'name' => 'Ледовая арена Haragun',
        ]);
        $category = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'name' => 'Чай',
            'slug' => 'tea',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::withQueryParams(['tab' => 'haragun-cafe'])
            ->test(CreateProduct::class)
            ->assertFormFieldIsHidden('kind')
            ->assertFormFieldIsHidden('food_section')
            ->assertFormFieldIsHidden('establishment_id')
            ->assertFormFieldIsVisible('brand_id')
            ->assertFormFieldIsVisible('sizes')
            ->fillForm([
                'name' => 'Чай зелёный',
                'sort_order' => 1,
                'is_active' => true,
                'brand_id' => $brand->id,
                'menu_category_id' => $category->id,
                'sizes' => [
                    [
                        'code' => 'S',
                        'volume_ml' => 300,
                        'price' => 790,
                        'is_default' => false,
                    ],
                    [
                        'code' => 'M',
                        'volume_ml' => 400,
                        'price' => 890,
                        'is_default' => true,
                    ],
                    [
                        'code' => 'L',
                        'volume_ml' => 500,
                        'price' => 990,
                        'is_default' => false,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('name', 'Чай зелёный')->first();

        $this->assertNotNull($product);
        $this->assertSame(ProductKind::Food, $product->kind);
        $this->assertNull($product->establishment_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->menu_category_id);
        $this->assertSame(890, $product->price);
        $this->assertSame(3, ProductSize::query()->where('product_id', $product->id)->count());
    }

    public function test_haragun_cafe_drinks_list_hides_tickets_and_foodcourt_snacks(): void
    {
        $admin = Admin::factory()->create();
        $brand = Brand::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
            'brand_id' => $brand->id,
        ]);
        $ice = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'type' => EstablishmentType::IceArena,
            'slug' => 'ice_rental',
        ]);
        $foodcourt = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'type' => EstablishmentType::IceArena,
            'slug' => 'foodcourt',
        ]);
        $coffee = MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::Coffee,
            'slug' => 'coffee',
            'name' => 'Кофе',
        ]);
        $ticket = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $ice->id,
            'kind' => ProductKind::Ticket,
            'name' => 'Взрослый Unique',
        ]);
        $snack = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $foodcourt->id,
            'kind' => ProductKind::Food,
            'name' => 'Хот-дог Unique',
        ]);
        $drink = Product::factory()->create([
            'establishment_id' => null,
            'brand_id' => $brand->id,
            'menu_category_id' => $coffee->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте Unique',
        ]);
        $skates = Product::factory()->create([
            'establishment_id' => $arena->id,
            'menu_category_id' => $ice->id,
            'kind' => ProductKind::RentalSkates,
            'name' => 'Коньки Unique',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ListProducts::class)
            ->set('activeTab', 'haragun-cafe')
            ->assertCanSeeTableRecords([$drink])
            ->assertCanNotSeeTableRecords([$ticket, $snack, $skates]);

        Livewire::test(ListProducts::class)
            ->set('activeTab', 'haragun-skates')
            ->assertCanSeeTableRecords([$skates])
            ->assertCanNotSeeTableRecords([$ticket, $snack, $drink]);

        Livewire::test(ListProducts::class)
            ->set('activeTab', 'haragun')
            ->assertCanSeeTableRecords([$ticket, $snack])
            ->assertCanNotSeeTableRecords([$drink, $skates]);
    }

    public function test_product_lists_hide_popular_and_banner_columns(): void
    {
        $admin = Admin::factory()->create();
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);
        $cafeBrand = Brand::factory()->create([
            'type' => EstablishmentType::Coffee,
        ]);
        Product::factory()->create([
            'establishment_id' => $arena->id,
            'kind' => ProductKind::Ticket,
            'name' => 'Взрослый Unique',
        ]);
        Product::factory()->create([
            'establishment_id' => null,
            'brand_id' => $cafeBrand->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте Unique',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::withQueryParams(['tab' => 'haragun'])
            ->test(ListProducts::class)
            ->assertDontSee('Популярн.')
            ->assertDontSee('Баннер');

        Livewire::withQueryParams(['tab' => 'cafes'])
            ->test(ListProducts::class)
            ->assertDontSee('Популярн.')
            ->assertDontSee('Баннер');
    }

    public function test_creating_a_haragun_ticket_hides_kind_and_section(): void
    {
        $admin = Admin::factory()->create();
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);
        $category = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'type' => EstablishmentType::IceArena,
            'slug' => 'ice_rental',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::withQueryParams(['tab' => 'haragun'])
            ->test(CreateProduct::class)
            ->assertFormFieldIsHidden('kind')
            ->assertFormFieldIsHidden('food_section')
            ->fillForm([
                'name' => 'Взрослый',
                'sort_order' => 1,
                'is_active' => true,
                'establishment_id' => $arena->id,
                'menu_category_id' => $category->id,
                'price' => 2500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('name', 'Взрослый')->first();

        $this->assertNotNull($product);
        $this->assertSame(ProductKind::Ticket, $product->kind);
        $this->assertNull($product->food_section);
        $this->assertSame(2500, $product->price);
    }

    public function test_creating_a_foodcourt_item_sets_food_kind_from_the_category(): void
    {
        $admin = Admin::factory()->create();
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);
        $category = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'type' => EstablishmentType::IceArena,
            'slug' => 'foodcourt',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::withQueryParams(['tab' => 'haragun'])
            ->test(CreateProduct::class)
            ->fillForm([
                'name' => 'Хот-дог',
                'sort_order' => 1,
                'is_active' => true,
                'establishment_id' => $arena->id,
                'menu_category_id' => $category->id,
                'price' => 1500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('name', 'Хот-дог')->first();

        $this->assertNotNull($product);
        $this->assertSame(ProductKind::Food, $product->kind);
        $this->assertNull($product->food_section);
    }

    public function test_creating_skates_stores_dynamic_sizes(): void
    {
        $admin = Admin::factory()->create();
        $arena = Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);
        $category = MenuCategory::factory()->create([
            'establishment_id' => $arena->id,
            'type' => EstablishmentType::IceArena,
            'slug' => 'ice_rental',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::withQueryParams(['tab' => 'haragun-skates'])
            ->test(CreateProduct::class)
            ->assertFormFieldIsHidden('kind')
            ->assertFormFieldIsHidden('food_section')
            ->assertFormFieldDoesNotExist('is_popular')
            ->assertFormFieldDoesNotExist('is_featured')
            ->assertFormFieldIsVisible('price')
            ->assertFormFieldIsVisible('skateSizes')
            ->fillForm([
                'name' => 'Коньки',
                'sort_order' => 10,
                'is_active' => true,
                'establishment_id' => $arena->id,
                'menu_category_id' => $category->id,
                'price' => 2500,
                'skateSizes' => [
                    [
                        'size' => 38,
                        'is_available' => true,
                    ],
                    [
                        'size' => 41,
                        'is_available' => true,
                    ],
                    [
                        'size' => 46,
                        'is_available' => false,
                    ],
                    [
                        'size' => null,
                        'is_available' => true,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('name', 'Коньки')->first();

        $this->assertNotNull($product);
        $this->assertSame(ProductKind::RentalSkates, $product->kind);
        $this->assertTrue($product->requires_size);
        $this->assertSame(38, $product->size_min);
        $this->assertSame(46, $product->size_max);
        $this->assertSame(3, SkateSize::query()->where('product_id', $product->id)->count());
        $this->assertDatabaseHas('skate_sizes', [
            'product_id' => $product->id,
            'size' => 38,
            'is_available' => true,
        ]);
        $this->assertDatabaseHas('skate_sizes', [
            'product_id' => $product->id,
            'size' => 46,
            'is_available' => false,
        ]);
    }

    public function test_skate_create_form_starts_with_sizes_from_35_to_46(): void
    {
        $admin = Admin::factory()->create();
        Establishment::factory()->create([
            'type' => EstablishmentType::IceArena,
        ]);
        MenuCategory::factory()->create([
            'type' => EstablishmentType::IceArena,
            'slug' => 'ice_rental',
        ]);

        $this->actingAs($admin, 'admin');

        $sizes = collect(
            Livewire::withQueryParams(['tab' => 'haragun-skates'])
                ->test(CreateProduct::class)
                ->instance()
                ->form
                ->getRawState()['skateSizes'] ?? []
        );

        $this->assertCount(12, $sizes);
        $this->assertSame(range(35, 46), $sizes->pluck('size')->values()->all());
        $this->assertTrue($sizes->every(fn (array $size): bool => (bool) $size['is_available']));
    }

    public function test_creating_a_modifier_stores_name_price_and_photo(): void
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();
        $category = ModifierCategory::factory()->create([
            'name' => 'Топпинги',
            'slug' => 'toppings',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateModifier::class)
            ->fillForm([
                'name' => 'Карамель',
                'sort_order' => 1,
                'is_active' => true,
                'modifier_category_id' => $category->id,
                'price' => 1250,
                'image_url' => UploadedFile::fake()->image('caramel.jpg', 120, 120),
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(ModifierResource::getUrl('index'));

        $modifier = Modifier::query()->where('name', 'Карамель')->first();

        $this->assertNotNull($modifier);
        $this->assertSame(1250, $modifier->price);
        $this->assertSame($category->id, $modifier->modifier_category_id);
        $this->assertTrue($modifier->is_active);
        $this->assertNotNull($modifier->image_url);
        Storage::disk('public')->assertExists($modifier->image_url);
    }

    public function test_creating_a_banner_stores_an_uploaded_photo(): void
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();
        $city = City::factory()->create([
            'name' => 'Алматы',
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateBanner::class)
            ->fillForm([
                'title' => '-20% на аренду коньков',
                'subtitle' => 'В будни до 17:00',
                'sort_order' => 1,
                'is_active' => true,
                'city_id' => $city->id,
                'image_url' => UploadedFile::fake()->image('banner.jpg', 320, 160),
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(BannerResource::getUrl('index'));

        $banner = Banner::query()->where('title', '-20% на аренду коньков')->first();

        $this->assertNotNull($banner);
        $this->assertSame($city->id, $banner->city_id);
        $this->assertSame(BannerPlacement::Hero, $banner->placement);
        $this->assertNotNull($banner->image_url);
        Storage::disk('public')->assertExists($banner->image_url);
    }

    public function test_orders_feedback_and_clients_lists_hide_create(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        $this->get(OrderResource::getUrl())
            ->assertOk()
            ->assertDontSee(OrderResource::getUrl('create'), false);

        $this->get(OrderFeedbackResource::getUrl())
            ->assertOk()
            ->assertDontSee(OrderFeedbackResource::getUrl('create'), false);

        $this->get(UserResource::getUrl())
            ->assertOk()
            ->assertDontSee(UserResource::getUrl('create'), false);
    }

    public function test_order_feedback_list_renders_array_tags(): void
    {
        $admin = Admin::factory()->create();

        OrderFeedback::factory()->create([
            'rating' => FeedbackRating::Negative,
            'likes' => ['taste'],
            'dislikes' => ['underheated', 'overheated'],
            'comment' => 'Холодный кофе',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(OrderFeedbackResource::getUrl())
            ->assertOk()
            ->assertSee('Вкус')
            ->assertSee('Недогрели')
            ->assertSee('Перегрели')
            ->assertSee('Холодный кофе');
    }
}
