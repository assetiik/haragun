<?php

namespace App\Filament\Resources\Establishments\Schemas;

use App\Enums\EstablishmentType;
use App\Filament\Forms\Components\LocationMapPicker;
use App\Filament\Support\LocalizedFields;
use App\Filament\Support\VenueSection;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EstablishmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'fi-fixed-positioning-context'])
            ->components([
                Section::make('Заведение')
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
                            ->label('Сортировка')
                            ->required()
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->columnSpan([
                                'md' => 3,
                            ]),
                        Toggle::make('is_active')
                            ->label('Активно')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan([
                                'md' => 2,
                            ]),
                        ...LocalizedFields::text('name', 'Название'),
                        Select::make('brand_id')
                            ->label('Бренд')
                            ->relationship(
                                'brand',
                                'name',
                                fn (Builder $query): Builder => VenueSection::constrainByType($query),
                            )
                            ->searchable()
                            ->preload()
                            ->hidden(fn (): bool => VenueSection::type() === EstablishmentType::IceArena)
                            ->dehydrated(fn (): bool => VenueSection::type() !== EstablishmentType::IceArena)
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        Select::make('city_id')
                            ->label('Город')
                            ->relationship('city', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('address')
                            ->label('Адрес · RU')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan([
                                'md' => 8,
                            ]),
                        ...LocalizedFields::text('address', 'Адрес'),
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(32)
                            ->columnSpan([
                                'md' => 4,
                            ]),
                        Select::make('type')
                            ->label('Тип')
                            ->options(collect(EstablishmentType::cases())->mapWithKeys(
                                fn (EstablishmentType $type): array => [$type->value => $type->label()]
                            )->all())
                            ->default(fn (): ?string => VenueSection::type()?->value)
                            ->required()
                            ->native(false)
                            ->hidden(fn (): bool => VenueSection::type() !== null)
                            ->columnSpan([
                                'md' => 4,
                            ]),
                        TimePicker::make('opens_at')
                            ->label('Открывается')
                            ->seconds(false)
                            ->required()
                            ->columnSpan([
                                'md' => 4,
                            ]),
                        TimePicker::make('closes_at')
                            ->label('Закрывается')
                            ->seconds(false)
                            ->required()
                            ->columnSpan([
                                'md' => 4,
                            ]),
                        TextInput::make('latitude')
                            ->label('Широта (lat)')
                            ->numeric()
                            ->step(0.0000001)
                            ->minValue(-90)
                            ->maxValue(90)
                            ->live(onBlur: true)
                            ->helperText('Для карты и расстояния во Flutter')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('longitude')
                            ->label('Долгота (lon)')
                            ->numeric()
                            ->step(0.0000001)
                            ->minValue(-180)
                            ->maxValue(180)
                            ->live(onBlur: true)
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        LocationMapPicker::make('location_map')
                            ->label('Точка на карте')
                            ->columnSpanFull(),
                        FileUpload::make('image_url')
                            ->label('Обложка')
                            ->helperText('Главное фото — будет первым в карусели')
                            ->image()
                            ->imagePreviewHeight('220')
                            ->panelLayout('integrated')
                            ->maxFiles(1)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('public')
                            ->directory('establishments')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->columnSpanFull(),
                        FileUpload::make('images')
                            ->label('Галерея')
                            ->helperText('Дополнительные фото после обложки')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->imagePreviewHeight('160')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('public')
                            ->directory('establishments/gallery')
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
