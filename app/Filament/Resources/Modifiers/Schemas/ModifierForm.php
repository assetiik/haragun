<?php

namespace App\Filament\Resources\Modifiers\Schemas;

use App\Filament\Support\LocalizedFields;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ModifierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'fi-fixed-positioning-context'])
            ->components([
                Section::make('Добавка')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Название · RU')
                            ->required()
                            ->maxLength(255)
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
                            ->label('Активен')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan([
                                'md' => 2,
                            ]),
                        ...LocalizedFields::text('name', 'Название'),
                        Select::make('modifier_category_id')
                            ->label('Категория')
                            ->relationship(
                                'category',
                                'name',
                                fn (Builder $query): Builder => $query->ordered(),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->helperText('Топпинги / Молоко / Сиропы — как в приложении')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('price')
                            ->label('Цена')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->default(0)
                            ->suffix('₸')
                            ->helperText('Доплата в тенге')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        FileUpload::make('image_url')
                            ->label('Фото')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('public')
                            ->directory('modifiers')
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
