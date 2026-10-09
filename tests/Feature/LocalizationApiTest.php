<?php

namespace Tests\Feature;

use App\Filament\Resources\Cities\Pages\CreateCity;
use App\Filament\Resources\LocalizationStrings\LocalizationStringResource;
use App\Filament\Resources\LocalizationStrings\Pages\CreateLocalizationString;
use App\Models\Admin;
use App\Models\Banner;
use App\Models\City;
use App\Models\LocalizationString;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocalizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_dictionary_returns_an_object_for_each_locale(): void
    {
        $this->get('/api/localizations')
            ->assertOk()
            ->assertSee('"ru":{}', false)
            ->assertSee('"kz":{}', false)
            ->assertSee('"en":{}', false);
    }

    public function test_localizations_endpoint_returns_strings_for_ru_kz_and_en(): void
    {
        LocalizationString::factory()->create([
            'section' => 'cart',
            'key' => 'empty_title',
            'ru' => 'Корзина пуста',
            'kz' => 'Себет бос',
            'en' => 'Cart is empty',
        ]);

        $payload = $this->getJson('/api/localizations')
            ->assertOk()
            ->assertJsonPath('locales', ['ru', 'kz', 'en'])
            ->assertJsonPath('default_locale', 'ru')
            ->json();

        $this->assertSame('Корзина пуста', $payload['strings']['ru']['cart.empty_title']);
        $this->assertSame('Себет бос', $payload['strings']['kz']['cart.empty_title']);
        $this->assertSame('Cart is empty', $payload['strings']['en']['cart.empty_title']);
    }

    public function test_locale_query_returns_only_that_language(): void
    {
        LocalizationString::factory()->create([
            'section' => 'home',
            'key' => 'title',
            'ru' => 'Главная',
            'kz' => 'Басты бет',
            'en' => 'Home',
        ]);

        $kazakh = $this->getJson('/api/localizations?locale=kz')
            ->assertOk()
            ->assertJsonPath('locale', 'kz')
            ->assertJsonMissingPath('strings.ru')
            ->json();

        $this->assertSame('Басты бет', $kazakh['strings']['home.title']);

        $english = $this->getJson('/api/localizations/en')
            ->assertOk()
            ->assertJsonPath('locale', 'en')
            ->json();

        $this->assertSame('Home', $english['strings']['home.title']);
    }

    public function test_unknown_locale_returns_422(): void
    {
        $this->getJson('/api/localizations?locale=fr')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');

        $this->getJson('/api/localizations/fr')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');
    }

    public function test_missing_translation_falls_back_to_russian(): void
    {
        LocalizationString::factory()->create([
            'section' => 'profile',
            'key' => 'logout',
            'ru' => 'Выйти',
            'kz' => '',
            'en' => 'Log out',
        ]);

        $payload = $this->getJson('/api/localizations/kz')
            ->assertOk()
            ->json();

        $this->assertSame('Выйти', $payload['strings']['profile.logout']);
    }

    public function test_admin_can_create_a_localization_string_in_three_languages(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateLocalizationString::class)
            ->fillForm([
                'section' => 'orders',
                'key' => 'status_ready',
                'ru' => 'Готов',
                'kz' => 'Дайын',
                'en' => 'Ready',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(LocalizationStringResource::getUrl('index'));

        $this->assertDatabaseHas('localization_strings', [
            'section' => 'orders',
            'key' => 'status_ready',
            'ru' => 'Готов',
            'kz' => 'Дайын',
            'en' => 'Ready',
        ]);
    }

    public function test_home_returns_the_city_and_banner_in_the_requested_locale(): void
    {
        $city = City::factory()->create([
            'name' => 'Алматы',
            'slug' => 'almaty',
            'i18n' => [
                'name' => ['kz' => 'Алматы', 'en' => 'Almaty'],
            ],
        ]);

        Banner::factory()->create([
            'city_id' => $city->id,
            'title' => 'Ночной сеанс',
            'i18n' => [
                'title' => ['kz' => 'Түнгі сеанс', 'en' => 'Night session'],
            ],
        ]);

        $this->withHeaders(['Accept-Language' => 'ru'])
            ->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('city.name', 'Алматы')
            ->assertJsonPath('header_images.0.title', 'Ночной сеанс');

        $this->getJson('/api/home?locale=en')
            ->assertOk()
            ->assertJsonPath('city.name', 'Almaty')
            ->assertJsonPath('header_images.0.title', 'Night session');

        $this->getJson('/api/home?locale=kz')
            ->assertOk()
            ->assertJsonPath('header_images.0.title', 'Түнгі сеанс');
    }

    public function test_admin_saves_three_languages_on_the_city_form(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(CreateCity::class)
            ->fillForm([
                'name' => 'Астана',
                'i18n' => [
                    'name' => [
                        'kz' => 'Астана',
                        'en' => 'Astana',
                    ],
                ],
                'sort_order' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $city = City::query()->where('name', 'Астана')->first();

        $this->assertNotNull($city);
        $this->assertSame('Астана', $city->i18n['name']['kz']);
        $this->assertSame('Astana', $city->i18n['name']['en']);
    }
}
