<?php

namespace App\Models;

use App\Enums\EstablishmentType;
use App\Support\Slugger;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'city_id',
    'name',
    'slug',
    'type',
    'icon_key',
    'accent_color',
    'is_active',
    'sort_order',
])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EstablishmentType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function establishments(): HasMany
    {
        return $this->hasMany(Establishment::class);
    }

    public function addresses(): HasMany
    {
        return $this->establishments();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    #[Scope]
    protected function iceArena(Builder $query): void
    {
        $query->where('type', EstablishmentType::IceArena);
    }

    #[Scope]
    protected function coffee(Builder $query): void
    {
        $query->where('type', EstablishmentType::Coffee);
    }

    public function requiresAddressSelection(): bool
    {
        return $this->establishments->where('is_active', true)->count() > 1;
    }

    public function nearestEstablishment(?float $latitude, ?float $longitude): ?Establishment
    {
        $addresses = $this->establishments->where('is_active', true)->values();

        if ($addresses->isEmpty()) {
            return null;
        }

        if ($latitude === null || $longitude === null) {
            return $addresses->sortBy('sort_order')->first();
        }

        return $addresses
            ->sortBy(fn (Establishment $establishment) => $establishment->distanceKm($latitude, $longitude) ?? PHP_FLOAT_MAX)
            ->first();
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand): void {
            if (blank($brand->slug) || $brand->isDirty('name')) {
                $brand->slug = Slugger::unique(
                    $brand->name,
                    self::class,
                    ['city_id' => $brand->city_id],
                    $brand->id,
                    'brand',
                );
            }
        });
    }
}
