<?php

namespace App\Models;

use App\Enums\AppLocale;
use Database\Factories\LocalizationStringFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'section',
    'key',
    'ru',
    'kz',
    'en',
])]
class LocalizationString extends Model
{
    /** @use HasFactory<LocalizationStringFactory> */
    use HasFactory;

    public function dictionaryKey(): string
    {
        return $this->section.'.'.$this->key;
    }

    public function valueFor(AppLocale $locale): ?string
    {
        foreach ([$locale, AppLocale::Ru, AppLocale::En] as $candidate) {
            $value = $this->{$candidate->value};

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array{locales: list<string>, default_locale: string, strings: array<string, object>}|array{locale: string, strings: object}
     */
    public static function dictionary(?AppLocale $locale = null): array
    {
        $rows = static::query()->orderBy('section')->orderBy('key')->get();

        if ($locale !== null) {
            return [
                'locale' => $locale->value,
                'strings' => (object) static::mapFor($rows, $locale),
            ];
        }

        $strings = [];

        foreach (AppLocale::cases() as $case) {
            $strings[$case->value] = (object) static::mapFor($rows, $case);
        }

        return [
            'locales' => array_map(
                fn (AppLocale $case): string => $case->value,
                AppLocale::cases(),
            ),
            'default_locale' => AppLocale::Ru->value,
            'strings' => $strings,
        ];
    }

    /**
     * @param  Collection<int, self>  $rows
     * @return array<string, string>
     */
    private static function mapFor(Collection $rows, AppLocale $locale): array
    {
        $map = [];

        foreach ($rows as $row) {
            $value = $row->valueFor($locale);

            if ($value === null) {
                continue;
            }

            $map[$row->dictionaryKey()] = $value;
        }

        return $map;
    }
}
