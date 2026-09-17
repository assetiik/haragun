<?php

namespace App\Filament\Resources\Establishments\Schemas;

use App\Enums\EstablishmentType;
use App\Filament\Support\VenueSection;
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
                            ->label('Название')
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
                            ->label('Адрес')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan([
                                'md' => 8,
                            ]),
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
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
            ]);
    }
}
