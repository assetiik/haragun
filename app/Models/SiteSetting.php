<?php

namespace App\Models;

use App\Models\Concerns\HasI18n;
use App\Support\StoredUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'about_title',
    'about_version',
    'about_text',
    'contacts_title',
    'contacts_phone',
    'contacts_phone_raw',
    'contacts_email',
    'contacts_address',
    'contacts_hours',
    'privacy_policy_title',
    'privacy_policy_documents',
    'offer_title',
    'offer_documents',
    'i18n',
])]
class SiteSetting extends Model
{
    use HasI18n;

    /**
     * @return list<string>
     */
    protected function translatableAttributes(): array
    {
        return [
            'about_title',
            'about_text',
            'contacts_title',
            'contacts_address',
            'contacts_hours',
            'privacy_policy_title',
            'offer_title',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'privacy_policy_documents' => 'array',
            'offer_documents' => 'array',
            'i18n' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'about_title' => config('haragun.about.title', 'О приложении'),
            'about_version' => config('haragun.about.version'),
            'about_text' => config('haragun.about.text'),
            'contacts_title' => config('haragun.contacts.title', 'Контакты'),
            'contacts_phone' => config('haragun.contacts.phone'),
            'contacts_phone_raw' => config('haragun.contacts.phone_raw'),
            'contacts_email' => config('haragun.contacts.email'),
            'contacts_address' => config('haragun.contacts.address'),
            'contacts_hours' => config('haragun.contacts.hours'),
            'privacy_policy_title' => config('haragun.privacy_policy.title', 'Политика конфиденциальности'),
            'privacy_policy_documents' => [],
            'offer_title' => config('haragun.offer.title', 'Оферта'),
            'offer_documents' => [],
        ]);
    }

    /**
     * @return array{title: string, version: ?string, text: ?string}
     */
    public function aboutPayload(): array
    {
        return [
            'title' => $this->about_title ?: 'О приложении',
            'version' => $this->about_version,
            'text' => $this->about_text,
        ];
    }

    /**
     * @return array{title: string, phone: ?string, phone_raw: ?string, email: ?string, address: ?string, hours: ?string}
     */
    public function contactsPayload(): array
    {
        return [
            'title' => $this->contacts_title ?: 'Контакты',
            'phone' => $this->contacts_phone,
            'phone_raw' => $this->contacts_phone_raw,
            'email' => $this->contacts_email,
            'address' => $this->contacts_address,
            'hours' => $this->contacts_hours,
        ];
    }

    /**
     * @return array{title: string, documents: list<array{url: string, name: string}>}
     */
    public function privacyPolicyPayload(): array
    {
        return $this->documentsPayload(
            $this->privacy_policy_title,
            $this->privacy_policy_documents,
            'Политика конфиденциальности',
        );
    }

    /**
     * @return array{title: string, documents: list<array{url: string, name: string}>}
     */
    public function offerPayload(): array
    {
        return $this->documentsPayload(
            $this->offer_title,
            $this->offer_documents,
            'Оферта',
        );
    }

    /**
     * @param  list<mixed>|null  $documents
     * @return array{title: string, documents: list<array{url: string, name: string}>}
     */
    private function documentsPayload(?string $title, ?array $documents, string $fallbackTitle): array
    {
        $paths = collect($documents ?? [])
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values();

        return [
            'title' => $title ?: $fallbackTitle,
            'documents' => $paths
                ->map(function (string $path): array {
                    return [
                        'url' => StoredUrl::public($path) ?? $path,
                        'name' => basename($path),
                    ];
                })
                ->all(),
        ];
    }
}
