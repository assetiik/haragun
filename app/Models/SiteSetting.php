<?php

namespace App\Models;

use App\Models\Concerns\HasI18n;
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
        return ['about_title', 'about_text', 'contacts_title', 'contacts_address', 'contacts_hours'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
}
