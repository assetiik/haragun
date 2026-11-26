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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductStockApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_exposes_stock_fields_and_keeps_out_of_stock_products(): void
    {
        [$establishment, $latte, $croissant] = $this->seedProducts();

        $latte->update(['stock_quantity' => 3]);
        $croissant->update(['stock_quantity' => 0]);

        $response = $this->getJson("/api/establishments/{$establishment->id}/menu");

        $response->assertOk();

        $products = collect($response->json('products'));
        $latteCard = $products->firstWhere('id', $latte->id);
        $croissantCard = $products->firstWhere('id', $croissant->id);

        $this->assertNotNull($latteCard);
        $this->assertSame(3, $latteCard['stock_quantity']);
        $this->assertTrue($latteCard['in_stock']);

        $this->assertNotNull($croissantCard);
        $this->assertSame(0, $croissantCard['stock_quantity']);
        $this->assertFalse($croissantCard['in_stock']);

        $this->getJson("/api/establishments/{$establishment->id}/menu/products/{$croissant->id}")
            ->assertOk()
            ->assertJsonPath('stock_quantity', 0)
            ->assertJsonPath('in_stock', false);

        $this->getJson("/api/establishments/{$establishment->id}/menu/products/{$latte->id}")
            ->assertOk()
            ->assertJsonPath('stock_quantity', 3)
            ->assertJsonPath('in_stock', true);
    }

    public function test_null_stock_means_unlimited_and_in_stock(): void
    {
        [$establishment, $latte] = $this->seedProducts();

        $this->getJson("/api/establishments/{$establishment->id}/menu/products/{$latte->id}")
            ->assertOk()
            ->assertJsonPath('stock_quantity', null)
            ->assertJsonPath('in_stock', true);
    }

    public function test_cannot_add_out_of_stock_product_to_cart(): void
    {
        [$establishment, $latte, , $size] = $this->seedProducts();
        $latte->update(['stock_quantity' => 0]);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_cannot_add_more_than_available_stock(): void
    {
        [$establishment, $latte, , $size] = $this->seedProducts();
        $latte->update(['stock_quantity' => 2]);
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson("/api/establishments/{$establishment->id}/cart/items", [
            'product_id' => $latte->id,
            'size_id' => $size->id,
            'quantity' => 1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    /**
     * @return array{0: Establishment, 1: Product, 2: Product, 3: ProductSize}
     */
    private function seedProducts(): array
    {
        $city = City::factory()->create();
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::Coffee,
        ]);
        $establishment = Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'type' => EstablishmentType::Coffee,
        ]);

        $coffee = MenuCategory::factory()->create([
            'type' => EstablishmentType::Coffee,
            'slug' => 'coffee',
            'name' => 'Кофе',
        ]);
        $snacks = MenuCategory::factory()->create([
            'type' => EstablishmentType::Coffee,
            'slug' => 'snacks',
            'name' => 'Закуски',
        ]);

        $latte = Product::factory()->create([
            'establishment_id' => null,
            'brand_id' => $brand->id,
            'menu_category_id' => $coffee->id,
            'kind' => ProductKind::Food,
            'name' => 'Латте',
            'price' => 1690,
            'is_popular' => true,
            'stock_quantity' => null,
        ]);

        $croissant = Product::factory()->create([
            'establishment_id' => null,
            'brand_id' => $brand->id,
            'menu_category_id' => $snacks->id,
            'kind' => ProductKind::Food,
            'name' => 'Круассан',
            'price' => 1200,
            'is_popular' => true,
            'stock_quantity' => null,
        ]);

        $size = ProductSize::factory()->create([
            'product_id' => $latte->id,
            'code' => 'M',
            'label' => '400 мл',
            'price' => 1690,
            'is_default' => true,
        ]);

        return [$establishment, $latte, $croissant, $size];
    }
}
