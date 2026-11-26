<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Filament\Pages\ManageWelcomeCoffeeGift;
use App\Models\Admin;
use App\Models\Brand;
use App\Models\City;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WelcomeGiftProductAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_choose_welcome_gift_product_from_sidebar_page(): void
    {
        $admin = Admin::factory()->create();
        $city = City::factory()->create();
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::Coffee,
        ]);
        $category = MenuCategory::factory()->create([
            'type' => EstablishmentType::Coffee,
            'slug' => 'coffee',
        ]);

        $espresso = Product::factory()->create([
            'brand_id' => $brand->id,
            'establishment_id' => null,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Food,
            'name' => 'Эспрессо',
            'is_welcome_espresso' => true,
            'is_active' => true,
        ]);

        $latte = Product::factory()->create([
            'brand_id' => $brand->id,
            'establishment_id' => null,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте',
            'is_welcome_espresso' => false,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        $this->get(ManageWelcomeCoffeeGift::getUrl())
            ->assertOk()
            ->assertSee('Подарок при регистрации');

        Livewire::test(ManageWelcomeCoffeeGift::class)
            ->assertFormSet([
                'product_id' => $espresso->id,
            ])
            ->fillForm([
                'product_id' => $latte->id,
            ])
            ->call('save')
            ->assertNotified();

        $this->assertFalse($espresso->fresh()->is_welcome_espresso);
        $this->assertTrue($latte->fresh()->is_welcome_espresso);
        $this->assertSame(1, Product::query()->where('is_welcome_espresso', true)->count());
    }

    public function test_admin_can_clear_welcome_gift_product(): void
    {
        $admin = Admin::factory()->create();
        $city = City::factory()->create();
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::Coffee,
        ]);
        $category = MenuCategory::factory()->create([
            'type' => EstablishmentType::Coffee,
            'slug' => 'coffee',
        ]);

        $espresso = Product::factory()->create([
            'brand_id' => $brand->id,
            'establishment_id' => null,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Food,
            'name' => 'Эспрессо',
            'is_welcome_espresso' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageWelcomeCoffeeGift::class)
            ->fillForm([
                'product_id' => null,
            ])
            ->call('save')
            ->assertNotified();

        $this->assertFalse($espresso->fresh()->is_welcome_espresso);
        $this->assertSame(0, Product::query()->where('is_welcome_espresso', true)->count());
    }
}
