<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EstablishmentImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_establishment_show_returns_photos(): void
    {
        Storage::fake('public');

        $city = City::factory()->create();
        $arena = Establishment::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::IceArena,
            'name' => 'Ледовая арена Haragun',
            'image_url' => 'establishments/cover.jpg',
            'images' => ['establishments/gallery/1.jpg', 'establishments/gallery/2.jpg'],
        ]);

        $this->getJson("/api/establishments/{$arena->id}")
            ->assertOk()
            ->assertJsonPath('establishment.image_url', Storage::disk('public')->url('establishments/cover.jpg'))
            ->assertJsonPath('establishment.images.0', Storage::disk('public')->url('establishments/cover.jpg'))
            ->assertJsonPath('establishment.images.1', Storage::disk('public')->url('establishments/gallery/1.jpg'))
            ->assertJsonPath('establishment.images.2', Storage::disk('public')->url('establishments/gallery/2.jpg'));
    }

    public function test_home_haragun_uses_establishment_photo_when_brand_has_none(): void
    {
        Storage::fake('public');

        $city = City::factory()->create(['slug' => 'almaty']);
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::IceArena,
            'name' => 'Ледовая арена Haragun',
            'image_url' => null,
            'images' => null,
        ]);

        Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'type' => EstablishmentType::IceArena,
            'image_url' => 'establishments/arena.jpg',
            'images' => ['establishments/arena-2.jpg'],
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('haragun.0.image_url', Storage::disk('public')->url('establishments/arena.jpg'))
            ->assertJsonPath('haragun.0.images.0', Storage::disk('public')->url('establishments/arena.jpg'))
            ->assertJsonPath('haragun.0.images.1', Storage::disk('public')->url('establishments/arena-2.jpg'))
            ->assertJsonPath('haragun.0.nearest_address.image_url', Storage::disk('public')->url('establishments/arena.jpg'));
    }

    public function test_brand_carousel_puts_cover_first(): void
    {
        Storage::fake('public');

        $city = City::factory()->create(['slug' => 'almaty']);
        $brand = Brand::factory()->create([
            'city_id' => $city->id,
            'type' => EstablishmentType::Coffee,
            'name' => 'Кофейня «Москва»',
            'image_url' => 'brands/cover.jpg',
            'images' => ['brands/gallery/second.jpg'],
        ]);

        Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $brand->id,
            'type' => EstablishmentType::Coffee,
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('cafes.0.image_url', Storage::disk('public')->url('brands/cover.jpg'))
            ->assertJsonPath('cafes.0.images.0', Storage::disk('public')->url('brands/cover.jpg'))
            ->assertJsonPath('cafes.0.images.1', Storage::disk('public')->url('brands/gallery/second.jpg'));
    }
}
