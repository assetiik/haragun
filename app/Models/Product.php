<?php

namespace App\Models;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Concerns\HasI18n;
use App\Support\Money;
use App\Support\StoredUrl;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'establishment_id',
    'brand_id',
    'menu_category_id',
    'kind',
    'requires_size',
    'size_min',
    'size_max',
    'name',
    'price',
    'meta',
    'description',
    'image_url',
    'background_color',
    'is_popular',
    'is_featured',
    'food_section',
    'icon_key',
    'age_from',
    'is_active',
    'sort_order',
    'i18n',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasI18n;

    /**
     * @return list<string>
     */
    protected function translatableAttributes(): array
    {
        return ['name', 'description'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProductKind::class,
            'requires_size' => 'boolean',
            'size_min' => 'integer',
            'size_max' => 'integer',
            'price' => 'integer',
            'age_from' => 'integer',
            'is_popular' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'i18n' => 'array',
        ];
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function menuCategory(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function skateSizes(): HasMany
    {
        return $this->hasMany(SkateSize::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    public function modifiers(): BelongsToMany
    {
        return $this->belongsToMany(Modifier::class, 'modifier_product')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    #[Scope]
    protected function tickets(Builder $query): void
    {
        $query->where('kind', ProductKind::Ticket);
    }

    #[Scope]
    protected function rentals(Builder $query): void
    {
        $query->whereIn('kind', [
            ProductKind::RentalSkates,
            ProductKind::RentalHelmet,
        ]);
    }

    #[Scope]
    protected function food(Builder $query): void
    {
        $query->where('kind', ProductKind::Food);
    }

    #[Scope]
    protected function coffeeMenu(Builder $query): void
    {
        $query->food()->whereHas(
            'menuCategory',
            fn (Builder $categoryQuery): Builder => $categoryQuery->where('type', EstablishmentType::Coffee),
        );
    }

    #[Scope]
    protected function venueCart(Builder $query): void
    {
        $query->food()->whereHas(
            'menuCategory',
            fn (Builder $categoryQuery): Builder => $categoryQuery
                ->where('type', EstablishmentType::Coffee)
                ->orWhereIn('slug', ['foodcourt', 'snack']),
        );
    }

    #[Scope]
    protected function popular(Builder $query): void
    {
        $query->where('is_popular', true);
    }

    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /**
     * Brand-owned cafe drinks OR establishment-owned arena/foodcourt items.
     */
    #[Scope]
    protected function availableAt(Builder $query, Establishment $establishment): void
    {
        $query->where(function (Builder $inner) use ($establishment): void {
            $inner->where('establishment_id', $establishment->id);

            if ($establishment->brand_id !== null) {
                $inner->orWhere('brand_id', $establishment->brand_id);
            }
        });
    }

    public function belongsToVenue(Establishment $establishment): bool
    {
        if ($this->brand_id !== null) {
            return $establishment->brand_id === $this->brand_id;
        }

        return $this->establishment_id === $establishment->id;
    }

    public function priceLabel(): string
    {
        return Money::format($this->price);
    }

    public function publicImageUrl(): ?string
    {
        return StoredUrl::public($this->image_url);
    }

    public function foodSubtitle(): string
    {
        return collect([$this->priceLabel(), $this->meta])
            ->filter()
            ->implode(' · ');
    }

    public function defaultSize(): ?ProductSize
    {
        return $this->sizes->firstWhere('is_default', true) ?? $this->sizes->sortBy('sort_order')->first();
    }

    public function syncPriceFromDefaultSize(): void
    {
        $this->unsetRelation('sizes');

        $size = $this->defaultSize();

        if ($size === null) {
            return;
        }

        if ($this->price === $size->price && $this->meta === $size->label) {
            return;
        }

        $this->update([
            'price' => $size->price,
            'meta' => $size->label,
        ]);
    }

    public function rentalSubtitle(): string
    {
        if ($this->kind === ProductKind::RentalSkates) {
            $range = ($this->size_min && $this->size_max)
                ? "размеры {$this->size_min}–{$this->size_max}"
                : 'за пару';

            return "{$this->priceLabel()} за пару · {$range}";
        }

        if ($this->kind === ProductKind::RentalHelmet) {
            return "{$this->priceLabel()} · ".($this->meta ?: 'универсальный размер');
        }

        return collect([$this->priceLabel(), $this->meta])->filter()->implode(' · ');
    }

    public function syncSizeRangeFromSkateSizes(): void
    {
        $this->unsetRelation('skateSizes');

        $sizes = $this->skateSizes()->ordered()->pluck('size');
        $min = $sizes->isEmpty() ? null : (int) $sizes->min();
        $max = $sizes->isEmpty() ? null : (int) $sizes->max();

        if ($this->size_min === $min && $this->size_max === $max) {
            return;
        }

        $this->update([
            'size_min' => $min,
            'size_max' => $max,
            'requires_size' => $sizes->isNotEmpty(),
        ]);
    }
}
