<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteSettings;
use App\Models\Admin;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class OfferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_offer_endpoint_returns_documents(): void
    {
        Storage::fake('public');

        SiteSetting::query()->create([
            'about_title' => 'О приложении',
            'contacts_title' => 'Контакты',
            'offer_title' => 'Оферта',
            'offer_documents' => [
                'offer/offer.pdf',
                'offer/terms.docx',
            ],
        ]);

        $this->getJson('/api/offer')
            ->assertOk()
            ->assertJsonPath('title', 'Оферта')
            ->assertJsonPath('documents.0.url', Storage::disk('public')->url('offer/offer.pdf'))
            ->assertJsonPath('documents.0.name', 'offer.pdf')
            ->assertJsonPath('documents.1.url', Storage::disk('public')->url('offer/terms.docx'))
            ->assertJsonPath('documents.1.name', 'terms.docx');
    }

    public function test_offer_title_respects_locale(): void
    {
        SiteSetting::query()->create([
            'about_title' => 'О приложении',
            'contacts_title' => 'Контакты',
            'offer_title' => 'Оферта',
            'offer_documents' => [],
            'i18n' => [
                'offer_title' => [
                    'en' => 'Public Offer',
                    'kz' => 'Оферта',
                ],
            ],
        ]);

        $this->getJson('/api/offer?locale=en')
            ->assertOk()
            ->assertJsonPath('title', 'Public Offer')
            ->assertJsonPath('documents', []);

        $this->withHeaders(['Accept-Language' => 'kz'])
            ->getJson('/api/offer')
            ->assertOk()
            ->assertJsonPath('title', 'Оферта');
    }

    public function test_profile_includes_offer_documents(): void
    {
        Storage::fake('public');

        SiteSetting::query()->create([
            'about_title' => 'О приложении',
            'contacts_title' => 'Контакты',
            'offer_title' => 'Публичная оферта',
            'offer_documents' => ['offer/offer.pdf'],
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('offer.title', 'Публичная оферта')
            ->assertJsonPath('offer.documents.0.name', 'offer.pdf')
            ->assertJsonPath(
                'offer.documents.0.url',
                Storage::disk('public')->url('offer/offer.pdf'),
            );
    }

    public function test_admin_can_upload_offer_documents(): void
    {
        Storage::fake('public');

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManageSiteSettings::class)
            ->fillForm([
                'about_title' => 'О приложении',
                'about_version' => '1.0.0',
                'about_text' => 'About',
                'contacts_title' => 'Контакты',
                'contacts_phone' => '+7 700 000 00 00',
                'contacts_phone_raw' => '77000000000',
                'contacts_email' => 'support@haragun.kz',
                'contacts_address' => 'Алматы',
                'contacts_hours' => '09:00–22:00',
                'privacy_policy_title' => 'Политика конфиденциальности',
                'offer_title' => 'Оферта',
                'offer_documents' => [
                    UploadedFile::fake()->create('offer.pdf', 120, 'application/pdf'),
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = SiteSetting::query()->first();

        $this->assertNotNull($setting);
        $this->assertSame('Оферта', $setting->offer_title);
        $this->assertIsArray($setting->offer_documents);
        $this->assertCount(1, $setting->offer_documents);
        Storage::disk('public')->assertExists($setting->offer_documents[0]);
    }
}
