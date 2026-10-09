<?php

namespace Tests\Feature;

use App\Enums\BannerPlacement;
use App\Enums\EstablishmentType;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\Banners\Pages\CreateBanner;
use App\Filament\Resources\PromoTiles\Pages\CreatePromoTile;
use App\Models\Admin;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\City;
use App\Models\Establishment;
use App\Models\PromoTile;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class SiteSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_returns_header_images_promo_tiles_and_brand_photos(): void
    {
        Storage::fake('public');

        $city = City::factory()->create([
            'name' => 'Алматы',
            'slug' => 'almaty',
        ]);

        Banner::factory()->create([
            'city_id' => $city->id,
            'placement' => BannerPlacement::Hero,
            'title' => 'ещё до -25%',
            'subtitle' => 'по промокоду',
            'button_label' => 'Узнать больше →',
            'image_url' => 'banners/hero.jpg',
            'sort_order' => 1,
        ]);

        PromoTile::factory()->create([
            'city_id' => $city->id,
            'title' => '-20% на коньки в будни',
            'image_url' => 'promo-tiles/promo.jpg',
            'sort_order' => 1,
        ]);

        $cafe = Brand::factory()->create([
            'city_id' => $city->id,
            'name' => 'Haragun coffee express',
            'subtitle' => 'Кофе, завтраки, десерты',
            'type' => EstablishmentType::Coffee,
            'image_url' => 'brands/cover.jpg',
            'images' => ['brands/gallery/1.jpg', 'brands/gallery/2.jpg'],
        ]);

        Establishment::factory()->create([
            'city_id' => $city->id,
            'brand_id' => $cafe->id,
            'type' => EstablishmentType::Coffee,
        ]);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('header_images.0.title', 'ещё до -25%')
            ->assertJsonPath('header_images.0.image_url', Storage::disk('public')->url('banners/hero.jpg'))
            ->assertJsonPath('header_images.0.button_label', 'Узнать больше →')
            ->assertJsonPath('promo_tiles.0.title', '-20% на коньки в будни')
            ->assertJsonPath('promo_tiles.0.image_url', Storage::disk('public')->url('promo-tiles/promo.jpg'))
            ->assertJsonPath('cafes.0.name', 'Haragun coffee express')
            ->assertJsonPath('cafes.0.subtitle', 'Кофе, завтраки, десерты')
            ->assertJsonPath('cafes.0.image_url', Storage::disk('public')->url('brands/cover.jpg'))
            ->assertJsonPath('cafes.0.images.0', Storage::disk('public')->url('brands/cover.jpg'))
            ->assertJsonPath('cafes.0.images.1', Storage::disk('public')->url('brands/gallery/1.jpg'))
            ->assertJsonPath('cafes.0.images.2', Storage::disk('public')->url('brands/gallery/2.jpg'));
    }

    public function test_profile_reads_about_and_contacts_from_site_settings(): void
    {
        SiteSetting::query()->create([
            'about_title' => 'О приложении',
            'about_version' => '1.2.0',
            'about_text' => 'Текст из админки',
            'contacts_title' => 'Контакты',
            'contacts_phone' => '+7 701 111 22 33',
            'contacts_phone_raw' => '77011112233',
            'contacts_email' => 'hello@haragun.kz',
            'contacts_address' => 'Алматы, Достык 240',
            'contacts_hours' => '10:00–22:00',
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('about.title', 'О приложении')
            ->assertJsonPath('about.version', '1.2.0')
            ->assertJsonPath('about.text', 'Текст из админки')
            ->assertJsonPath('contacts.phone', '+7 701 111 22 33')
            ->assertJsonPath('contacts.email', 'hello@haragun.kz')
            ->assertJsonPath('contacts.address', 'Алматы, Достык 240');
    }

    public function test_admin_can_save_site_settings(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'about_title' => 'О приложении',
                'about_version' => '2.0.0',
                'about_text' => 'Новый текст',
                'contacts_title' => 'Контакты',
                'contacts_phone' => '+7 700 000 00 00',
                'contacts_phone_raw' => '77000000000',
                'contacts_email' => 'support@haragun.kz',
                'contacts_address' => 'Алматы',
                'contacts_hours' => 'Ежедневно 09:00–22:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('site_settings', [
            'about_version' => '2.0.0',
            'about_text' => 'Новый текст',
            'contacts_email' => 'support@haragun.kz',
        ]);
    }

    public function test_creating_header_slide_stores_photo(): void
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();
        $city = City::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateBanner::class)
            ->fillForm([
                'title' => 'ещё до -25%',
                'subtitle' => 'по промокоду на первый сеанс',
                'button_label' => 'Узнать больше →',
                'sort_order' => 1,
                'is_active' => true,
                'city_id' => $city->id,
                'image_url' => UploadedFile::fake()->image('hero.jpg', 320, 160),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::query()->where('title', 'ещё до -25%')->first();

        $this->assertNotNull($banner);
        $this->assertSame(BannerPlacement::Hero, $banner->placement);
        $this->assertNotNull($banner->image_url);
        Storage::disk('public')->assertExists($banner->image_url);
    }

    public function test_creating_promo_tile_stores_photo_and_description(): void
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();
        $city = City::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreatePromoTile::class)
            ->fillForm([
                'title' => 'Ночной сеанс с 21:00',
                'sort_order' => 3,
                'is_active' => true,
                'city_id' => $city->id,
                'image_url' => UploadedFile::fake()->image('tile.jpg', 320, 160),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tile = PromoTile::query()->where('title', 'Ночной сеанс с 21:00')->first();

        $this->assertNotNull($tile);
        $this->assertNotNull($tile->image_url);
        Storage::disk('public')->assertExists($tile->image_url);
    }
}
