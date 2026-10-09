<?php

namespace App\Filament\Resources\ModifierCategories\Schemas;

use App\Filament\Support\LocalizedFields;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ModifierCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Категория')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Название · RU')
                            ->required()
                            ->maxLength(120)
                            ->helperText('Например: Топпинги, Молоко, Сиропы')
                            ->columnSpan([
                                'md' => 7,
                            ]),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->columnSpan([
                                'md' => 3,
                            ]),
                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan([
                                'md' => 2,
                            ]),
                        ...LocalizedFields::text('name', 'Название', 120),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->maxLength(120)
                            ->helperText('Оставьте пустым — заполнится автоматически')
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
            ]);
    }
}
