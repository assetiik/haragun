<?php

namespace App\Models;

use App\Enums\EstablishmentType;
use App\Models\Concerns\HasI18n;
use App\Support\Slugger;
use App\Support\StoredUrl;
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
    'subtitle',
    'slug',
    'type',
    'icon_key',
    'accent_color',
    'image_url',
    'images',
    'is_active',
    'sort_order',
    'i18n',
])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    use HasI18n;

    /**
     * @return list<string>
     */
    protected function translatableAttributes(): array
    {
        return ['name', 'subtitle'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EstablishmentType::class,
            'images' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'i18n' => 'array',
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function addresses(): HasMany
    {
        return $this->establishments();
    }

    public function publicImageUrl(): ?string
    {
        return StoredUrl::public($this->image_url);
    }

    /**
     * @return list<string>
     */
    public function publicImages(): array
    {
        return collect($this->images ?? [])
            ->map(fn (mixed $path): ?string => StoredUrl::public(is_string($path) ? $path : null))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Cover first, then gallery (no duplicates) — for Flutter carousels.
     *
     * @return list<string>
     */
    public function carouselImages(): array
    {
        return collect([$this->publicImageUrl(), ...$this->publicImages()])
            ->filter()
            ->unique()
            ->values()
            ->all();
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
                    $brand->stored('name'),
                    self::class,
                    ['city_id' => $brand->city_id],
                    $brand->id,
                    'brand',
                );
            }
        });
    }
}
