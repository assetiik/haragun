<?php

namespace App\Filament\Resources\MenuCategories\Schemas;

use App\Enums\MenuCatalog;
use App\Filament\Support\LocalizedFields;
use App\Filament\Support\VenueSection;
use App\Models\Establishment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MenuCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'fi-fixed-positioning-context'])
            ->components([
                Section::make('Категория')
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
                            ->label('Активна')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan([
                                'md' => 2,
                            ]),
                        ...LocalizedFields::text('name', 'Название', 120),
                        Select::make('catalog')
                            ->label('Раздел')
                            ->options([
                                MenuCatalog::Drinks->value => 'Напитки',
                                MenuCatalog::Snacks->value => 'Перекус',
                            ])
                            ->placeholder('Экран арены')
                            ->helperText('Напитки и перекус — категории Haragun. У кофеен свои категории.')
                            ->native(false)
                            ->live()
                            ->visible(fn ($livewire): bool => ! self::isCoffeeForm($livewire))
                            ->dehydrated(fn ($livewire): bool => ! self::isCoffeeForm($livewire))
                            ->columnSpanFull(),
                        Select::make('establishment_id')
                            ->label('Заведение')
                            ->relationship(
                                'establishment',
                                'name',
                                fn (Builder $query): Builder => VenueSection::constrainByType($query),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Establishment $record): string => $record->nameWithAddress()
                            )
                            ->native(false)
                            ->preload()
                            ->required(fn ($livewire, Get $get): bool => self::showsVenueFields($livewire, $get))
                            ->dehydrated(fn ($livewire, Get $get): bool => self::showsVenueFields($livewire, $get))
                            ->visible(fn ($livewire, Get $get): bool => self::showsVenueFields($livewire, $get))
                            ->default(fn (): ?int => VenueSection::defaultEstablishmentId())
                            ->columnSpanFull(),
                        Select::make('slug')
                            ->label('Тип в приложении')
                            ->options([
                                'ice_rental' => 'Лёд и аренда',
                                'foodcourt' => 'Фудкорт',
                                'snack' => 'Перекус',
                            ])
                            ->required(fn ($livewire, Get $get): bool => self::showsVenueFields($livewire, $get))
                            ->dehydrated(fn ($livewire, Get $get): bool => self::showsVenueFields($livewire, $get))
                            ->native(false)
                            ->visible(fn ($livewire, Get $get): bool => self::showsVenueFields($livewire, $get))
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        Toggle::make('requires_booking')
                            ->label('Нужна бронь')
                            ->inline(false)
                            ->default(false)
                            ->visible(fn ($livewire, Get $get): bool => self::showsVenueFields($livewire, $get))
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('prep_minutes')
                            ->label('Минут на приготовление')
                            ->numeric()
                            ->default(10)
                            ->visible(fn ($livewire, Get $get): bool => self::showsVenueFields($livewire, $get))
                            ->columnSpan([
                                'md' => 6,
                            ]),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
                Section::make('Тексты на экране')
                    ->collapsed()
                    ->columnSpanFull()
                    ->visible(fn ($livewire, Get $get): bool => self::showsVenueFields($livewire, $get))
                    ->schema([
                        TextInput::make('section_title')
                            ->label('Заголовок секции · RU')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('section_subtitle')
                            ->label('Подзаголовок секции · RU')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('header_title')
                            ->label('Заголовок шапки · RU')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('header_subtitle')
                            ->label('Подзаголовок шапки · RU')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        ...LocalizedFields::text('section_title', 'Заголовок секции'),
                        ...LocalizedFields::text('section_subtitle', 'Подзаголовок секции'),
                        ...LocalizedFields::text('header_title', 'Заголовок шапки'),
                        ...LocalizedFields::text('header_subtitle', 'Подзаголовок шапки'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
            ]);
    }

    private static function isCoffeeForm(object $livewire): bool
    {
        return VenueSection::isCafeContext(
            $livewire->record ?? null,
            $livewire->venueTab ?? null,
        );
    }

    private static function showsVenueFields(object $livewire, Get $get): bool
    {
        return ! self::isCoffeeForm($livewire) && blank($get('catalog'));
    }
}
