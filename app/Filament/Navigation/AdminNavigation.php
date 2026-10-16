<?php

namespace App\Filament\Navigation;

use App\Enums\EstablishmentType;
use App\Enums\MenuCatalog;
use App\Enums\ProductKind;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Establishments\EstablishmentResource;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use App\Filament\Resources\ModifierCategories\ModifierCategoryResource;
use App\Filament\Resources\Modifiers\ModifierResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\VenueSection;
use App\Models\Brand;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use BackedEnum;
use Closure;
use Filament\Navigation\NavigationItem;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

use function Filament\Support\original_request;

final class AdminNavigation
{
    /**
     * @return array<NavigationItem>
     */
    public static function items(): array
    {
        return [
            self::venueItem(
                label: 'Арена',
                group: 'Haragun',
                icon: Heroicon::OutlinedSparkles,
                resource: EstablishmentResource::class,
                tab: VenueSection::Haragun,
                sort: 1,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof Establishment ? $record->type : null,
            ),
            self::venueItem(
                label: 'Категории',
                group: 'Haragun',
                icon: Heroicon::OutlinedRectangleGroup,
                resource: MenuCategoryResource::class,
                tab: VenueSection::Haragun,
                sort: 2,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof MenuCategory ? $record->type : null,
                recordMatches: fn (Model $record): bool => $record instanceof MenuCategory
                    && $record->catalog === null,
            ),
            self::venueItem(
                label: 'Категории блюд',
                group: 'Haragun',
                icon: Heroicon::OutlinedBookOpen,
                resource: MenuCategoryResource::class,
                tab: VenueSection::HaragunFood,
                sort: 3,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof MenuCategory ? $record->type : null,
                recordMatches: fn (Model $record): bool => $record instanceof MenuCategory
                    && $record->catalog !== null,
            ),
            self::venueItem(
                label: 'Меню',
                group: 'Haragun',
                icon: Heroicon::OutlinedTicket,
                resource: ProductResource::class,
                tab: VenueSection::Haragun,
                sort: 4,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof Product
                    ? ($record->brand?->type ?? $record->establishment?->type)
                    : null,
                recordMatches: fn (Model $record): bool => $record instanceof Product
                    && $record->kind !== ProductKind::RentalSkates
                    && $record->menuCategory?->type !== EstablishmentType::Coffee
                    && $record->menuCategory?->catalog === null,
            ),
            self::venueItem(
                label: 'Коньки',
                group: 'Haragun',
                icon: Heroicon::OutlinedSquares2x2,
                resource: ProductResource::class,
                tab: VenueSection::HaragunSkates,
                sort: 5,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof Product
                    ? ($record->brand?->type ?? $record->establishment?->type)
                    : null,
                recordMatches: fn (Model $record): bool => $record instanceof Product
                    && $record->kind === ProductKind::RentalSkates,
            ),
            self::venueItem(
                label: 'Напитки',
                group: 'Haragun',
                icon: Heroicon::OutlinedCube,
                resource: ProductResource::class,
                tab: VenueSection::HaragunCafe,
                sort: 6,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof Product
                    ? ($record->brand?->type ?? $record->establishment?->type)
                    : null,
                recordMatches: fn (Model $record): bool => $record instanceof Product
                    && ($record->menuCategory?->type === EstablishmentType::Coffee
                        || $record->menuCategory?->catalog === MenuCatalog::Drinks),
            ),
            self::venueItem(
                label: 'Еда',
                group: 'Haragun',
                icon: Heroicon::OutlinedShoppingBag,
                resource: ProductResource::class,
                tab: VenueSection::HaragunMeals,
                sort: 7,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof Product
                    ? ($record->brand?->type ?? $record->establishment?->type)
                    : null,
                recordMatches: fn (Model $record): bool => $record instanceof Product
                    && $record->menuCategory?->catalog === MenuCatalog::Snacks,
            ),
            self::venueItem(
                label: 'Бренды',
                group: 'Кофейни',
                icon: Heroicon::OutlinedTag,
                resource: BrandResource::class,
                tab: VenueSection::Cafes,
                sort: 1,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof Brand ? $record->type : null,
            ),
            self::venueItem(
                label: 'Точки',
                group: 'Кофейни',
                icon: Heroicon::OutlinedBuildingStorefront,
                resource: EstablishmentResource::class,
                tab: VenueSection::Cafes,
                sort: 2,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof Establishment ? $record->type : null,
            ),
            self::venueItem(
                label: 'Категории',
                group: 'Кофейни',
                icon: Heroicon::OutlinedRectangleGroup,
                resource: MenuCategoryResource::class,
                tab: VenueSection::Cafes,
                sort: 3,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof MenuCategory ? $record->type : null,
            ),
            self::venueItem(
                label: 'Напитки',
                group: 'Кофейни',
                icon: Heroicon::OutlinedCube,
                resource: ProductResource::class,
                tab: VenueSection::Cafes,
                sort: 4,
                typeFromRecord: fn (Model $record): ?EstablishmentType => $record instanceof Product
                    ? ($record->brand?->type ?? $record->establishment?->type)
                    : null,
            ),
            self::resourceItem(
                label: 'Добавки',
                group: 'Кофейни',
                icon: Heroicon::OutlinedAdjustmentsHorizontal,
                resource: ModifierResource::class,
                sort: 5,
            ),
            self::resourceItem(
                label: 'Категории добавок',
                group: 'Кофейни',
                icon: Heroicon::OutlinedSquares2x2,
                resource: ModifierCategoryResource::class,
                sort: 6,
            ),
        ];
    }

    /**
     * @param  class-string  $resource
     * @param  Closure(Model): ?EstablishmentType  $typeFromRecord
     * @param  (Closure(Model): bool)|null  $recordMatches
     */
    private static function venueItem(
        string $label,
        string $group,
        BackedEnum $icon,
        string $resource,
        string $tab,
        int $sort,
        Closure $typeFromRecord,
        ?Closure $recordMatches = null,
    ): NavigationItem {
        $expectedType = VenueSection::type($tab);

        return NavigationItem::make($label)
            ->key($group.'-'.$tab.'-'.$resource)
            ->group($group)
            ->icon($icon)
            ->sort($sort)
            ->url(fn (): string => $resource::getUrl().'?tab='.$tab)
            ->isActiveWhen(function () use ($resource, $tab, $expectedType, $typeFromRecord, $recordMatches): bool {
                $request = original_request();

                if (! $request->routeIs($resource::getRouteBaseName().'.*')) {
                    return false;
                }

                $queryTab = $request->query('tab');

                if (is_string($queryTab)) {
                    return $queryTab === $tab;
                }

                $record = $request->route('record');

                if ($record === null || $expectedType === null) {
                    return false;
                }

                $model = $record instanceof Model
                    ? $record
                    : $resource::getModel()::query()->find($record);

                if ($model === null) {
                    return false;
                }

                if ($typeFromRecord($model) !== $expectedType) {
                    return false;
                }

                return $recordMatches === null || $recordMatches($model);
            });
    }

    /**
     * @param  class-string  $resource
     */
    private static function resourceItem(
        string $label,
        string $group,
        BackedEnum $icon,
        string $resource,
        int $sort,
    ): NavigationItem {
        return NavigationItem::make($label)
            ->key($group.'-'.$resource)
            ->group($group)
            ->icon($icon)
            ->sort($sort)
            ->url(fn (): string => $resource::getUrl())
            ->isActiveWhen(fn (): bool => original_request()->routeIs($resource::getRouteBaseName().'.*'));
    }
}
