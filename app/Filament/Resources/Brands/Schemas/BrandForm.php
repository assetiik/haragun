<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Enums\EstablishmentType;
use App\Filament\Support\LocalizedFields;
use App\Filament\Support\VenueSection;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'fi-fixed-positioning-context'])
            ->components([
                Section::make('Бренд')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Название · RU')
                            ->required()
                            ->maxLength(120)
                            ->columnSpan([
                                'md' => 7,
                            ]),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0)
                            ->required()
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
                        ...LocalizedFields::text('name', 'Название', 120),
                        Select::make('city_id')
                            ->label('Город')
                            ->relationship('city', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        Select::make('type')
                            ->label('Тип')
                            ->options(collect(EstablishmentType::cases())->mapWithKeys(
                                fn (EstablishmentType $type): array => [$type->value => $type->label()]
                            ))
                            ->default(fn (): ?string => VenueSection::type()?->value)
                            ->required()
                            ->hidden(fn (): bool => VenueSection::type() !== null)
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('subtitle')
                            ->label('Подзаголовок · RU')
                            ->placeholder('Кофе, завтраки, десерты')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        ...LocalizedFields::text('subtitle', 'Подзаголовок'),
                        FileUpload::make('image_url')
                            ->label('Обложка')
                            ->helperText('Главное фото — будет первым в карусели')
                            ->image()
                            ->imagePreviewHeight('220')
                            ->panelLayout('integrated')
                            ->maxFiles(1)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('public')
                            ->directory('brands')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->columnSpanFull(),
                        FileUpload::make('images')
                            ->label('Галерея (карусель)')
                            ->helperText('Дополнительные фото после обложки')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->imagePreviewHeight('160')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('public')
                            ->directory('brands/gallery')
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
