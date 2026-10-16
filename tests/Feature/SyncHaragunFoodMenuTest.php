<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\MenuCatalog;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Support\HaragunFoodCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncHaragunFoodMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_fills_meals_into_existing_food_categories(): void
    {
        $brand = Brand::factory()->create([
            'type' => EstablishmentType::IceArena,
            'name' => 'Ледовая арена Haragun',
        ]);

        foreach ([
            ['name' => 'Хот Дог & Бургеры', 'slug' => 'hot-dogs'],
            ['name' => 'Горячие закуски', 'slug' => 'hot-apps'],
            ['name' => 'Соуса', 'slug' => 'sauces'],
            ['name' => 'Выпечка', 'slug' => 'bakery'],
            ['name' => 'Суп', 'slug' => 'soup'],
            ['name' => 'Десерт', 'slug' => 'dessert'],
        ] as $index => $category) {
            MenuCategory::factory()->create([
                'establishment_id' => null,
                'type' => EstablishmentType::IceArena,
                'catalog' => MenuCatalog::Snacks,
                'name' => $category['name'],
                'slug' => $category['slug'],
                'sort_order' => $index + 1,
            ]);
        }

        $result = HaragunFoodCatalog::sync($brand);

        $this->assertSame(20, $result['created']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame(20, Product::query()->where('brand_id', $brand->id)->where('kind', ProductKind::Food)->count());
        $this->assertDatabaseHas('products', [
            'brand_id' => $brand->id,
            'name' => 'Смэш бургер с говяжьим беконом',
            'price' => 7900,
            'food_section' => 'Хот Дог & Бургеры',
            'establishment_id' => null,
        ]);
        $this->assertDatabaseHas('products', [
            'brand_id' => $brand->id,
            'name' => 'Том ям с морепродуктами',
            'price' => 4900,
            'food_section' => 'Суп',
        ]);
    }

    public function test_sync_command_reports_missing_categories(): void
    {
        Brand::factory()->create([
            'type' => EstablishmentType::IceArena,
            'name' => 'Ледовая арена Haragun',
        ]);

        MenuCategory::factory()->create([
            'establishment_id' => null,
            'type' => EstablishmentType::IceArena,
            'catalog' => MenuCatalog::Snacks,
            'name' => 'Суп',
            'slug' => 'soup',
        ]);

        $this->artisan('haragun:sync-food-menu')
            ->expectsOutputToContain('Создано: 1')
            ->expectsOutputToContain('Хот Дог & Бургеры')
            ->assertSuccessful();
    }
}
