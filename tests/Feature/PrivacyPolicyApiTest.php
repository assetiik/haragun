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

class PrivacyPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_privacy_policy_endpoint_returns_documents(): void
    {
        Storage::fake('public');

        SiteSetting::query()->create([
            'about_title' => 'О приложении',
            'contacts_title' => 'Контакты',
            'privacy_policy_title' => 'Политика конфиденциальности',
            'privacy_policy_documents' => [
                'privacy-policy/policy.pdf',
                'privacy-policy/consent.docx',
            ],
        ]);

        $this->getJson('/api/privacy-policy')
            ->assertOk()
            ->assertJsonPath('title', 'Политика конфиденциальности')
            ->assertJsonPath('documents.0.url', Storage::disk('public')->url('privacy-policy/policy.pdf'))
            ->assertJsonPath('documents.0.name', 'policy.pdf')
            ->assertJsonPath('documents.1.url', Storage::disk('public')->url('privacy-policy/consent.docx'))
            ->assertJsonPath('documents.1.name', 'consent.docx');
    }

    public function test_privacy_policy_title_respects_locale(): void
    {
        SiteSetting::query()->create([
            'about_title' => 'О приложении',
            'contacts_title' => 'Контакты',
            'privacy_policy_title' => 'Политика конфиденциальности',
            'privacy_policy_documents' => [],
            'i18n' => [
                'privacy_policy_title' => [
                    'en' => 'Privacy Policy',
                    'kz' => 'Құпиялылық саясаты',
                ],
            ],
        ]);

        $this->getJson('/api/privacy-policy?locale=en')
            ->assertOk()
            ->assertJsonPath('title', 'Privacy Policy')
            ->assertJsonPath('documents', []);

        $this->withHeaders(['Accept-Language' => 'kz'])
            ->getJson('/api/privacy-policy')
            ->assertOk()
            ->assertJsonPath('title', 'Құпиялылық саясаты');
    }

    public function test_profile_includes_privacy_policy_documents(): void
    {
        Storage::fake('public');

        SiteSetting::query()->create([
            'about_title' => 'О приложении',
            'contacts_title' => 'Контакты',
            'privacy_policy_title' => 'Политика',
            'privacy_policy_documents' => ['privacy-policy/policy.pdf'],
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('privacy_policy.title', 'Политика')
            ->assertJsonPath('privacy_policy.documents.0.name', 'policy.pdf')
            ->assertJsonPath(
                'privacy_policy.documents.0.url',
                Storage::disk('public')->url('privacy-policy/policy.pdf'),
            );
    }

    public function test_admin_can_upload_privacy_policy_documents(): void
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
                'privacy_policy_documents' => [
                    UploadedFile::fake()->create('policy.pdf', 120, 'application/pdf'),
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = SiteSetting::query()->first();

        $this->assertNotNull($setting);
        $this->assertSame('Политика конфиденциальности', $setting->privacy_policy_title);
        $this->assertIsArray($setting->privacy_policy_documents);
        $this->assertCount(1, $setting->privacy_policy_documents);
        Storage::disk('public')->assertExists($setting->privacy_policy_documents[0]);
    }
}
