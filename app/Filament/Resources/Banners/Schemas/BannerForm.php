<?php

namespace App\Filament\Resources\Banners\Schemas;

use App\Filament\Support\LocalizedFields;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'fi-fixed-positioning-context'])
            ->components([
                Section::make('Слайд шапки')
                    ->description('Каждая запись — один слайд карусели на главной. Добавьте несколько слайдов для листания.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('title')
                            ->label('Заголовок · RU')
                            ->helperText('Например: «ещё до -25%»')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan([
                                'md' => 7,
                            ]),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->helperText('1, 2, 3… — порядок в карусели')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->columnSpan([
                                'md' => 3,
                            ]),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan([
                                'md' => 2,
                            ]),
                        ...LocalizedFields::text('title', 'Заголовок'),
                        Select::make('city_id')
                            ->label('Город')
                            ->relationship('city', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Все города')
                            ->columnSpanFull(),
                        Textarea::make('subtitle')
                            ->label('Подзаголовок · RU')
                            ->helperText('Текст под заголовком на слайде')
                            ->rows(2)
                            ->columnSpanFull(),
                        ...LocalizedFields::textarea('subtitle', 'Подзаголовок'),
                        TextInput::make('button_label')
                            ->label('Текст кнопки · RU')
                            ->placeholder('Узнать больше →')
                            ->maxLength(100)
                            ->columnSpanFull(),
                        ...LocalizedFields::text('button_label', 'Текст кнопки', 100),
                        FileUpload::make('image_url')
                            ->label('Фото слайда')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('public')
                            ->directory('banners')
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
