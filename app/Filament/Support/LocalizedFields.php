<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

final class LocalizedFields
{
    /**
     * @return list<TextInput>
     */
    public static function text(string $attribute, string $label, int $maxLength = 255): array
    {
        return [
            TextInput::make("i18n.{$attribute}.kz")
                ->label($label.' · KZ')
                ->maxLength($maxLength)
                ->columnSpan([
                    'md' => 6,
                ]),
            TextInput::make("i18n.{$attribute}.en")
                ->label($label.' · EN')
                ->maxLength($maxLength)
                ->columnSpan([
                    'md' => 6,
                ]),
        ];
    }

    /**
     * @return list<Textarea>
     */
    public static function textarea(string $attribute, string $label): array
    {
        return [
            Textarea::make("i18n.{$attribute}.kz")
                ->label($label.' · KZ')
                ->rows(3)
                ->columnSpanFull(),
            Textarea::make("i18n.{$attribute}.en")
                ->label($label.' · EN')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }
}
