<?php

namespace App\Filament\Resources\Brands\Schemas;

use App\Enums\EstablishmentType;
use App\Filament\Support\VenueSection;
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
                            ->label('Название')
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
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
            ]);
    }
}
