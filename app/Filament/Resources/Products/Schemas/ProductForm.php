<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\EstablishmentType;
use App\Filament\Support\LocalizedFields;
use App\Filament\Support\VenueSection;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Modifier;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'fi-fixed-positioning-context'])
            ->components([
                Section::make(fn ($livewire): string => match (true) {
                    self::isCoffeeForm($livewire) => 'Напиток',
                    self::isMealsForm($livewire) => 'Еда',
                    self::isSkateForm($livewire) => 'Коньки',
                    default => 'Позиция',
                })
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
                        Select::make('brand_id')
                            ->label('Бренд')
                            ->relationship(
                                'brand',
                                'name',
                                fn (Builder $query): Builder => $query
                                    ->when(
                                        VenueSection::type(),
                                        fn (Builder $brandQuery, EstablishmentType $type): Builder => $brandQuery->where('type', $type),
                                    )
                                    ->ordered(),
                            )
                            ->native(false)
                            ->preload()
                            ->searchable()
                            ->required(fn ($livewire): bool => self::isBrandMenuForm($livewire))
                            ->visible(fn ($livewire): bool => self::isBrandMenuForm($livewire))
                            ->dehydrated(fn ($livewire): bool => self::isBrandMenuForm($livewire))
                            ->helperText('Меню общее для всех точек бренда')
                            ->columnSpan([
                                'md' => 6,
                            ]),
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
                            ->required(fn ($livewire): bool => ! self::isBrandMenuForm($livewire))
                            ->visible(fn ($livewire): bool => ! self::isBrandMenuForm($livewire))
                            ->dehydrated(fn ($livewire): bool => ! self::isBrandMenuForm($livewire))
                            ->default(fn (): ?int => VenueSection::defaultEstablishmentId())
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        Select::make('menu_category_id')
                            ->label('Категория меню')
                            ->relationship(
                                'menuCategory',
                                'name',
                                fn (Builder $query): Builder => VenueSection::constrainMenuCategories($query),
                            )
                            ->helperText(function ($livewire): ?string {
                                $tab = $livewire->venueTab ?? VenueSection::fromRequest();

                                return match ($tab) {
                                    VenueSection::HaragunCafe => 'Категории с типом «Напитки» из Категории блюд',
                                    VenueSection::HaragunMeals => 'Категории с типом «Еда» из Категории блюд',
                                    default => null,
                                };
                            })
                            ->native(false)
                            ->preload()
                            ->live()
                            ->required()
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        Select::make('kind')
                            ->hidden()
                            ->dehydrated(false),
                        TextInput::make('food_section')
                            ->label('Секция')
                            ->maxLength(120)
                            ->helperText('Например: Перекус или Напитки')
                            ->visible(fn ($livewire, Get $get): bool => ! self::isCoffeeForm($livewire) && self::categoryIsFoodCourt($get('menu_category_id')))
                            ->dehydrated(fn ($livewire, Get $get): bool => ! self::isCoffeeForm($livewire) && self::categoryIsFoodCourt($get('menu_category_id')))
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('price')
                            ->label(fn ($livewire, Get $get): string => self::usesDayPrice($livewire, $get) ? 'Цена в будни' : 'Цена')
                            ->required(fn ($livewire): bool => ! self::isCoffeeForm($livewire))
                            ->dehydrated(fn ($livewire): bool => ! self::isCoffeeForm($livewire))
                            ->visible(fn ($livewire): bool => ! self::isCoffeeForm($livewire))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->suffix('₸')
                            ->helperText(fn ($livewire, Get $get): string => self::usesDayPrice($livewire, $get)
                                ? 'Понедельник — пятница'
                                : 'Целое число в тенге')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        TextInput::make('weekend_price')
                            ->label('Цена в выходные')
                            ->required(fn ($livewire, Get $get): bool => self::usesDayPrice($livewire, $get))
                            ->visible(fn ($livewire, Get $get): bool => self::usesDayPrice($livewire, $get))
                            ->dehydrated(fn ($livewire, Get $get): bool => self::usesDayPrice($livewire, $get))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->suffix('₸')
                            ->helperText('Суббота и воскресенье')
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        Repeater::make('sizes')
                            ->label('Размеры')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->default(fn (): array => self::defaultCoffeeSizes())
                            ->afterStateHydrated(function (Repeater $component): void {
                                if (! self::isCoffeeForm($component->getLivewire()) || filled($component->getRawState())) {
                                    return;
                                }

                                $items = [];

                                foreach (self::defaultCoffeeSizes() as $size) {
                                    $items[$component->generateUuid()] = $size;
                                }

                                $component->rawState($items);
                            })
                            ->helperText('Если размера нет, оставьте цену пустой — он не сохранится.')
                            ->table([
                                TableColumn::make('Размер')
                                    ->width('8rem'),
                                TableColumn::make('Объём')
                                    ->width('8rem'),
                                TableColumn::make('Цена')
                                    ->width('9rem'),
                                TableColumn::make('По умолчанию')
                                    ->width('8rem'),
                            ])
                            ->schema([
                                Select::make('code')
                                    ->label('Размер')
                                    ->options([
                                        'S' => 'S',
                                        'M' => 'M',
                                        'L' => 'L',
                                    ])
                                    ->required(fn (Get $get): bool => filled($get('price')))
                                    ->distinct()
                                    ->native(false),
                                TextInput::make('volume_ml')
                                    ->label('Объём')
                                    ->required(fn (Get $get): bool => filled($get('price')))
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->suffix('мл')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if (filled($state)) {
                                            $set('label', ((int) $state).' мл');
                                        }
                                    }),
                                TextInput::make('price')
                                    ->label('Цена')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->nullable()
                                    ->suffix('₸'),
                                Toggle::make('is_default')
                                    ->label('По умолчанию')
                                    ->inline(false),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): ?array => self::prepareSizeData($data))
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Model $record): ?array {
                                $prepared = self::prepareSizeData($data);

                                if ($prepared === null) {
                                    $record->delete();
                                }

                                return $prepared;
                            })
                            ->addActionLabel('Добавить размер')
                            ->visible(fn ($livewire): bool => self::isCoffeeForm($livewire))
                            ->columnSpanFull(),
                        Repeater::make('skateSizes')
                            ->label('Размеры')
                            ->relationship()
                            ->default(fn (): array => self::defaultSkateSizes())
                            ->afterStateHydrated(function (Repeater $component): void {
                                if (! self::isSkateForm($component->getLivewire()) || filled($component->getRawState())) {
                                    return;
                                }

                                $items = [];

                                foreach (self::defaultSkateSizes() as $size) {
                                    $items[$component->generateUuid()] = $size;
                                }

                                $component->rawState($items);
                            })
                            ->helperText('Оставьте номер пустым, если этого размера нет — строка не сохранится. Можно добавлять любые размеры.')
                            ->table([
                                TableColumn::make('Размер')
                                    ->width('10rem'),
                                TableColumn::make('Есть в прокате')
                                    ->width('10rem'),
                            ])
                            ->schema([
                                TextInput::make('size')
                                    ->label('Размер')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(20)
                                    ->maxValue(60)
                                    ->nullable()
                                    ->distinct(),
                                Toggle::make('is_available')
                                    ->label('Есть')
                                    ->default(true)
                                    ->inline(false),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): ?array => self::prepareSkateSizeData($data))
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Model $record): ?array {
                                $prepared = self::prepareSkateSizeData($data);

                                if ($prepared === null) {
                                    $record->delete();
                                }

                                return $prepared;
                            })
                            ->addActionLabel('Добавить размер')
                            ->visible(fn ($livewire): bool => self::isSkateForm($livewire))
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Описание · RU')
                            ->rows(3)
                            ->columnSpanFull(),
                        ...LocalizedFields::textarea('description', 'Описание'),
                        FileUpload::make('image_url')
                            ->label('Фото')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->maxSize(10240)
                            ->columnSpanFull(),
                        Select::make('modifiers')
                            ->label('Добавки')
                            ->relationship(
                                'modifiers',
                                'name',
                                fn (Builder $query): Builder => $query
                                    ->orderBy($query->qualifyColumn('sort_order'))
                                    ->orderBy($query->qualifyColumn('name')),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Modifier $record): string => $record->name.' · '.$record->priceLabel()
                            )
                            ->multiple()
                            ->searchable()
                            ->native(false)
                            ->preload()
                            ->visible(fn ($livewire): bool => self::isCoffeeForm($livewire))
                            ->dehydrated(fn ($livewire): bool => self::isCoffeeForm($livewire))
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
            ]);
    }

    /**
     * @return list<array{code: string, volume_ml: int, label: string, is_default: bool}>
     */
    public static function defaultCoffeeSizes(): array
    {
        return [
            [
                'code' => 'S',
                'volume_ml' => 300,
                'label' => '300 мл',
                'is_default' => false,
            ],
            [
                'code' => 'M',
                'volume_ml' => 400,
                'label' => '400 мл',
                'is_default' => true,
            ],
            [
                'code' => 'L',
                'volume_ml' => 500,
                'label' => '500 мл',
                'is_default' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function prepareSizeData(array $data): ?array
    {
        if (! filled($data['price'] ?? null)) {
            return null;
        }

        if (filled($data['volume_ml'] ?? null)) {
            $data['label'] = ((int) $data['volume_ml']).' мл';
        }

        return $data;
    }

    /**
     * @return list<array{size: int, is_available: bool}>
     */
    public static function defaultSkateSizes(): array
    {
        return collect(range(35, 46))
            ->map(fn (int $size): array => [
                'size' => $size,
                'is_available' => true,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function prepareSkateSizeData(array $data): ?array
    {
        if (! filled($data['size'] ?? null)) {
            return null;
        }

        $data['size'] = (int) $data['size'];
        $data['is_available'] = (bool) ($data['is_available'] ?? true);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFoodSection(array $data): array
    {
        if (! self::categoryIsFoodCourt($data['menu_category_id'] ?? null)) {
            $data['food_section'] = null;
        }

        return $data;
    }

    private static function categoryIsFoodCourt(mixed $categoryId): bool
    {
        if (! filled($categoryId)) {
            return false;
        }

        return MenuCategory::query()->find($categoryId)?->isFoodCourt() ?? false;
    }

    private static function isCoffeeForm(object $livewire): bool
    {
        return VenueSection::isCafeContext(
            $livewire->record ?? null,
            $livewire->venueTab ?? null,
        );
    }

    private static function isBrandMenuForm(object $livewire): bool
    {
        $tab = $livewire->venueTab ?? VenueSection::fromRequest();

        if (in_array($tab, [VenueSection::Cafes, VenueSection::HaragunCafe, VenueSection::HaragunMeals], true)) {
            return true;
        }

        $record = $livewire->record ?? null;

        return $record instanceof Product && $record->brand_id !== null;
    }

    private static function isMealsForm(object $livewire): bool
    {
        return VenueSection::isMealsContext(
            $livewire->record ?? null,
            $livewire->venueTab ?? null,
        );
    }

    private static function isSkateForm(object $livewire): bool
    {
        return VenueSection::isSkateContext(
            $livewire->record ?? null,
            $livewire->venueTab ?? null,
        );
    }

    private static function usesDayPrice(object $livewire, Get $get): bool
    {
        if (self::isCoffeeForm($livewire) || self::isMealsForm($livewire)) {
            return false;
        }

        if (self::isSkateForm($livewire)) {
            return true;
        }

        $record = $livewire->record ?? null;

        if ($record instanceof Product) {
            return $record->kind->chargesByDay();
        }

        $categoryId = $get('menu_category_id');

        if (filled($categoryId)) {
            $category = MenuCategory::query()->find($categoryId);

            if ($category !== null) {
                return ! $category->isFoodCourt();
            }
        }

        return ($livewire->venueTab ?? VenueSection::fromRequest()) === VenueSection::Haragun;
    }
}
