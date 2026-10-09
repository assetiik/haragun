<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BrandAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_coffee_brand_returns_address_list_and_map_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 12:00:00'));

        [$brand, $first, $second] = $this->seedMoscowBrand();

        $response = $this->getJson("/api/brands/{$brand->id}/addresses?lat=43.256&lng=76.928");

        $response
            ->assertOk()
            ->assertJsonPath('brand.name', 'Кофейня «Москва»')
            ->assertJsonPath('addresses.0.address', 'ул. Сейфуллина 154')
            ->assertJsonPath('addresses.0.is_open', true)
            ->assertJsonPath('addresses.0.opens_at', '10:00')
            ->assertJsonPath('addresses.0.closes_at', '18:00')
            ->assertJsonPath('addresses.0.phone', '77777777777')
            ->assertJsonPath('addresses.1.address', 'ул. Қабанбай батыр 128')
            ->assertJsonPath('selected_address_id', $first->id)
            ->assertJsonPath('selected_address.address', 'ул. Сейфуллина 154')
            ->assertJsonPath('map.markers.0.id', $first->id)
            ->assertJsonPath('map.markers.0.is_selected', true);

        $this->assertCount(2, $response->json('addresses'));
        $this->assertSame($second->id, $response->json('addresses.1.id'));
    }

    public function test_can_select_specific_address_for_map_sheet(): void
    {
        [$brand, $first, $second] = $this->seedMoscowBrand();

        $this->getJson("/api/brands/{$brand->id}/addresses?selected_id={$second->id}")
            ->assertOk()
            ->assertJsonPath('selected_address_id', $second->id)
            ->assertJsonPath('selected_address.address', 'ул. Қабанбай батыр 128')
            ->assertJsonPath('addresses.1.is_selected', true);

        $this->getJson("/api/brands/{$brand->id}/addresses/{$second->id}")
            ->assertOk()
            ->assertJsonPath('id', $second->id)
            ->assertJsonPath('address', 'ул. Қабанбай батыр 128')
            ->assertJsonPath('is_selected', true);
    }

    public function test_home_marks_coffee_brand_as_requiring_address_selection(): void
    {
        [$brand] = $this->seedMoscowBrand();

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('brands.0.id', $brand->id)
            ->assertJsonPath('brands.0.requires_address_selection', true)
            ->assertJsonPath('brands.0.addresses_count', 2);
    }

    /**
     * @return array{0: Brand, 1: Establishment, 2: Establishment}
     */
    private function seedMoscowBrand(): array
    {
        $city = City::factory()->create(['slug' => 'almaty']);

        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'name' => 'Кофейня «Москва»',
            'slug' => 'coffee-moscow',
            'type' => EstablishmentType::Coffee,
            'icon_key' => 'coffee',
            'accent_color' => '#C26E40',
        ]);

        $first = Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'name' => 'Кофейня «Москва»',
            'address' => 'ул. Сейфуллина 154',
            'phone' => '77777777777',
            'type' => EstablishmentType::Coffee,
            'latitude' => 43.2560,
            'longitude' => 76.9280,
            'opens_at' => '10:00:00',
            'closes_at' => '18:00:00',
            'sort_order' => 1,
        ]);

        $second = Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'name' => 'Кофейня «Москва»',
            'address' => 'ул. Қабанбай батыр 128',
            'phone' => '77777777778',
            'type' => EstablishmentType::Coffee,
            'latitude' => 43.2389,
            'longitude' => 76.9455,
            'opens_at' => '09:00:00',
            'closes_at' => '22:00:00',
            'sort_order' => 2,
        ]);

        return [$brand, $first, $second];
    }
}
