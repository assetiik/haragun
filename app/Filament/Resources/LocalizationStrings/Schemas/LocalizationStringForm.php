<?php

namespace App\Filament\Resources\LocalizationStrings\Schemas;

use App\Models\LocalizationString;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class LocalizationStringForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Ключ')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('section')
                            ->label('Раздел')
                            ->required()
                            ->maxLength(40)
                            ->regex('/^[a-z0-9_]+$/')
                            ->helperText('Латиница: home, cart, profile. В API ключ будет section.key')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('key')
                            ->label('Ключ')
                            ->required()
                            ->maxLength(80)
                            ->regex('/^[a-z0-9_]+$/')
                            ->rules(fn (Get $get, ?LocalizationString $record): array => [
                                Rule::unique('localization_strings', 'key')
                                    ->where('section', (string) $get('section'))
                                    ->ignore($record?->id),
                            ])
                            ->helperText('Например: empty_title')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
                Section::make('Переводы')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('ru')
                            ->label('Русский')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('kz')
                            ->label('Қазақша')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('en')
                            ->label('English')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
