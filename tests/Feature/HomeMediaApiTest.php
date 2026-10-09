<?php

namespace Tests\Feature;

use App\Enums\BannerPlacement;
use App\Models\Banner;
use App\Models\City;
use App\Models\PromoTile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeMediaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_images_endpoint_returns_only_header_slides(): void
    {
        Storage::fake('public');

        $city = City::factory()->create(['slug' => 'almaty']);

        Banner::factory()->create([
            'city_id' => $city->id,
            'placement' => BannerPlacement::Hero,
            'title' => '-20% на аренду коньков',
            'subtitle' => 'В будни до 17:00',
            'button_label' => 'Узнать больше →',
            'image_url' => 'banners/hero.jpg',
            'sort_order' => 1,
        ]);

        Banner::factory()->create([
            'city_id' => $city->id,
            'placement' => BannerPlacement::Hero,
            'title' => 'ещё до -25%',
            'subtitle' => 'по промокоду',
            'image_url' => 'banners/hero-2.jpg',
            'sort_order' => 2,
        ]);

        PromoTile::factory()->create([
            'city_id' => $city->id,
            'title' => 'Не должно быть в шапке',
            'image_url' => 'promo-tiles/x.jpg',
        ]);

        $this->getJson('/api/header-images?city_id='.$city->id)
            ->assertOk()
            ->assertJsonPath('section', 'header')
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.type', 'header')
            ->assertJsonPath('items.0.title', '-20% на аренду коньков')
            ->assertJsonPath('items.0.subtitle', 'В будни до 17:00')
            ->assertJsonPath('items.0.button_label', 'Узнать больше →')
            ->assertJsonPath('items.0.image_url', Storage::disk('public')->url('banners/hero.jpg'))
            ->assertJsonPath('items.1.title', 'ещё до -25%')
            ->assertJsonMissing(['title' => 'Не должно быть в шапке']);
    }

    public function test_promo_tiles_endpoint_returns_only_promo_tiles(): void
    {
        Storage::fake('public');

        $city = City::factory()->create(['slug' => 'almaty']);

        Banner::factory()->create([
            'city_id' => $city->id,
            'placement' => BannerPlacement::Hero,
            'title' => 'qqqqq',
            'image_url' => 'banners/hero.jpg',
        ]);

        PromoTile::factory()->create([
            'city_id' => $city->id,
            'title' => '-20% на коньки в будни',
            'image_url' => 'promo-tiles/1.jpg',
            'sort_order' => 1,
        ]);

        PromoTile::factory()->create([
            'city_id' => $city->id,
            'title' => '6-й кофе в подарок',
            'image_url' => 'promo-tiles/2.jpg',
            'sort_order' => 2,
        ]);

        $this->getJson('/api/promo-tiles?city_id='.$city->id)
            ->assertOk()
            ->assertJsonPath('section', 'promo_tiles')
            ->assertJsonCount(2, 'items')
            ->assertJsonPath('items.0.type', 'promo')
            ->assertJsonPath('items.0.title', '-20% на коньки в будни')
            ->assertJsonPath('items.0.description', '-20% на коньки в будни')
            ->assertJsonPath('items.0.image_url', Storage::disk('public')->url('promo-tiles/1.jpg'))
            ->assertJsonPath('items.1.title', '6-й кофе в подарок')
            ->assertJsonMissing(['title' => 'qqqqq']);
    }
}
