<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_returns_city_banners_and_brands(): void
    {
        $city = City::factory()->create([
            'name' => 'Алматы',
            'slug' => 'almaty',
            'sort_order' => 1,
        ]);

        Banner::factory()->create([
            'city_id' => $city->id,
            'title' => '-20% на аренду коньков',
            'subtitle' => 'В будни до 17:00',
            'sort_order' => 1,
        ]);

        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'name' => 'Ледовая арена Haragun',
            'slug' => 'haragun-ice-arena',
            'type' => EstablishmentType::IceArena,
            'sort_order' => 1,
        ]);

        Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'name' => 'Ледовая арена Haragun',
            'address' => 'пр. Достык, 240',
            'type' => EstablishmentType::IceArena,
            'latitude' => 43.2335,
            'longitude' => 76.9560,
            'opens_at' => '10:00:00',
            'closes_at' => '22:00:00',
            'sort_order' => 1,
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('app_name', config('app.name'))
            ->assertJsonPath('city.name', 'Алматы')
            ->assertJsonPath('banners.0.title', '-20% на аренду коньков')
            ->assertJsonPath('brands.0.name', 'Ледовая арена Haragun')
            ->assertJsonPath('brands.0.type', 'ice_arena')
            ->assertJsonPath('brands.0.requires_address_selection', false)
            ->assertJsonPath('brands.0.nearest_address.address', 'пр. Достык, 240')
            ->assertJsonPath('user', null);
    }

    public function test_home_separates_haragun_from_cafes(): void
    {
        $city = City::factory()->create([
            'name' => 'Алматы',
            'slug' => 'almaty',
        ]);

        $arenaBrand = Brand::factory()->create([
            'city_id' => $city->id,
            'name' => 'Ледовая арена Haragun',
            'type' => EstablishmentType::IceArena,
            'sort_order' => 1,
        ]);
        Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $arenaBrand->id,
            'name' => 'Ледовая арена Haragun',
            'type' => EstablishmentType::IceArena,
        ]);

        $cafeBrand = Brand::factory()->create([
            'city_id' => $city->id,
            'name' => 'Кофейня «Москва»',
            'type' => EstablishmentType::Coffee,
            'sort_order' => 2,
        ]);
        Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $cafeBrand->id,
            'name' => 'Кофейня «Москва»',
            'type' => EstablishmentType::Coffee,
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonCount(1, 'haragun')
            ->assertJsonCount(1, 'cafes')
            ->assertJsonCount(2, 'brands')
            ->assertJsonPath('haragun.0.name', 'Ледовая арена Haragun')
            ->assertJsonPath('haragun.0.type', 'ice_arena')
            ->assertJsonPath('cafes.0.name', 'Кофейня «Москва»')
            ->assertJsonPath('cafes.0.type', 'coffee');
    }

    public function test_home_calculates_distance_when_coordinates_provided(): void
    {
        $city = City::factory()->create(['slug' => 'almaty']);
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'name' => 'Кофейня «Москва»',
            'type' => EstablishmentType::Coffee,
        ]);

        Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'name' => 'Кофейня «Москва»',
            'latitude' => 43.2567,
            'longitude' => 76.9286,
        ]);

        $response = $this->getJson('/api/home?lat=43.2380&lng=76.9450');

        $response->assertOk();
        $this->assertNotNull($response->json('brands.0.nearest_address.distance_km'));
        $this->assertStringContainsString('км', $response->json('brands.0.nearest_address.distance_label'));
    }

    public function test_home_includes_authenticated_user(): void
    {
        City::factory()->create(['slug' => 'almaty']);

        $user = User::factory()->create([
            'name' => 'Аружан',
            'phone' => '77071234567',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('user.name', 'Аружан')
            ->assertJsonPath('user.initials', 'А');
    }

    public function test_home_can_filter_by_city_id(): void
    {
        $almaty = City::factory()->create(['name' => 'Алматы', 'slug' => 'almaty', 'sort_order' => 1]);
        $astana = City::factory()->create(['name' => 'Астана', 'slug' => 'astana', 'sort_order' => 2]);

        $almatyBrand = Brand::factory()->create([
            'city_id' => $almaty->id,
            'name' => 'Алматинская точка',
        ]);
        Establishment::factory()->create([
            'city_id' => $almaty->id,
            'brand_id' => $almatyBrand->id,
            'name' => 'Алматинская точка',
        ]);

        $astanaBrand = Brand::factory()->create([
            'city_id' => $astana->id,
            'name' => 'Астанинская точка',
        ]);
        Establishment::factory()->create([
            'city_id' => $astana->id,
            'brand_id' => $astanaBrand->id,
            'name' => 'Астанинская точка',
        ]);

        $this->getJson('/api/home?city_id='.$astana->id)
            ->assertOk()
            ->assertJsonPath('city.name', 'Астана')
            ->assertJsonPath('brands.0.name', 'Астанинская точка')
            ->assertJsonCount(1, 'brands');
    }
}
