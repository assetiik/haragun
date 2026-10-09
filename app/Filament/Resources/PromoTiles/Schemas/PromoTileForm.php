<?php

namespace App\Filament\Resources\PromoTiles\Schemas;

use App\Filament\Support\LocalizedFields;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromoTileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'fi-fixed-positioning-context'])
            ->components([
                Section::make('Промо-плитка')
                    ->description('На главной: фото и короткий текст поверх фото')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label('Описание · RU')
                            ->helperText('Например: «-20% на коньки в будни»')
                            ->required()
                            ->maxLength(120)
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
                        ...LocalizedFields::text('title', 'Описание', 120),
                        Toggle::make('is_active')
                            ->label('Активна')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan([
                                'md' => 2,
                            ]),
                        Select::make('city_id')
                            ->label('Город')
                            ->relationship('city', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Все города')
                            ->columnSpanFull(),
                        FileUpload::make('image_url')
                            ->label('Фото')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('public')
                            ->directory('promo-tiles')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
            ]);
    }
}
