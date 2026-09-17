<?php

namespace App\Filament\Support;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

final class VenueSection
{
    public const Haragun = 'haragun';

    public const HaragunCafe = 'haragun-cafe';

    public const HaragunSkates = 'haragun-skates';

    public const Cafes = 'cafes';

    /**
     * @return list<string>
     */
    public static function tabs(): array
    {
        return [
            self::Haragun,
            self::HaragunCafe,
            self::HaragunSkates,
            self::Cafes,
        ];
    }

    public static function fromRequest(): ?string
    {
        $tab = request()->query('tab');

        if (in_array($tab, self::tabs(), true)) {
            return $tab;
        }

        $livewireTab = data_get(Livewire::current(), 'venueTab');

        if (in_array($livewireTab, self::tabs(), true)) {
            return $livewireTab;
        }

        return null;
    }

    public static function type(?string $section = null): ?EstablishmentType
    {
        return match ($section ?? self::fromRequest()) {
            self::Cafes => EstablishmentType::Coffee,
            self::Haragun, self::HaragunCafe, self::HaragunSkates => EstablishmentType::IceArena,
            default => null,
        };
    }

    public static function fromType(?EstablishmentType $type): ?string
    {
        return match ($type) {
            EstablishmentType::Coffee => self::Cafes,
            EstablishmentType::IceArena => self::Haragun,
            default => null,
        };
    }

    public static function fromRecord(?Model $record): ?string
    {
        if (
            $record instanceof Product
            && $record->establishment?->type === EstablishmentType::IceArena
            && $record->kind === ProductKind::RentalSkates
        ) {
            return self::HaragunSkates;
        }

        if (
            $record instanceof Product
            && $record->establishment?->type === EstablishmentType::IceArena
            && $record->menuCategory?->type === EstablishmentType::Coffee
        ) {
            return self::HaragunCafe;
        }

        $type = match (true) {
            $record instanceof Brand, $record instanceof Establishment => $record->type,
            $record instanceof MenuCategory => $record->type ?? $record->establishment?->type,
            $record instanceof Product => $record->establishment?->type,
            default => null,
        };

        return self::fromType($type);
    }

    public static function isCafeContext(?Model $record = null, ?string $tab = null): bool
    {
        $section = $tab ?? self::fromRequest();

        if (in_array($section, [self::Cafes, self::HaragunCafe], true)) {
            return true;
        }

        return in_array(self::fromRecord($record), [self::Cafes, self::HaragunCafe], true);
    }

    public static function isSkateContext(?Model $record = null, ?string $tab = null): bool
    {
        $section = $tab ?? self::fromRequest();

        if ($section === self::HaragunSkates) {
            return true;
        }

        return self::fromRecord($record) === self::HaragunSkates;
    }

    public static function defaultEstablishmentId(?EstablishmentType $type = null): ?int
    {
        $type ??= self::type();
        $query = Establishment::query()->ordered();

        if ($type !== null) {
            $query->where('type', $type);
        }

        $ids = $query->pluck('id');

        if ($ids->count() !== 1) {
            return null;
        }

        return (int) $ids->first();
    }

    public static function withTab(string $url, ?string $tab): string
    {
        if (! in_array($tab, self::tabs(), true) || str_contains($url, 'tab=')) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'tab='.$tab;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function constrainByType(Builder $query): Builder
    {
        $type = self::type();

        if ($type === null) {
            return $query;
        }

        return $query->where('type', $type);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function constrainByEstablishment(Builder $query, string $relation = 'establishment'): Builder
    {
        $type = self::type();

        if ($type === null) {
            return $query;
        }

        return $query->whereHas(
            $relation,
            fn (Builder $venueQuery): Builder => $venueQuery->where('type', $type),
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function constrainMenuCategories(Builder $query): Builder
    {
        $section = self::fromRequest();

        if (in_array($section, [self::Cafes, self::HaragunCafe], true)) {
            return $query->where('type', EstablishmentType::Coffee);
        }

        $type = self::type($section);

        if ($type === null) {
            return $query;
        }

        return $query->where('type', $type);
    }
}
