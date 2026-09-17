<?php

namespace App\Models;

use App\Enums\EstablishmentType;
use App\Support\Slugger;
use Database\Factories\MenuCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'establishment_id',
    'type',
    'name',
    'slug',
    'section_title',
    'section_subtitle',
    'header_title',
    'header_subtitle',
    'header_color',
    'prep_minutes',
    'requires_booking',
    'is_active',
    'sort_order',
])]
class MenuCategory extends Model
{
    /** @use HasFactory<MenuCategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EstablishmentType::class,
            'requires_booking' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'prep_minutes' => 'integer',
        ];
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isFoodCourt(): bool
    {
        return in_array($this->slug, ['foodcourt', 'snack'], true);
    }

    public function isCoffeeMenu(): bool
    {
        return in_array($this->slug, ['popular', 'tea', 'coffee', 'lemonade', 'menu'], true)
            || (! $this->requires_booking && ! $this->isFoodCourt());
    }

    #[Scope]
    protected function coffee(Builder $query): void
    {
        $query->where('type', EstablishmentType::Coffee);
    }

    #[Scope]
    protected function iceArena(Builder $query): void
    {
        $query->where('type', EstablishmentType::IceArena);
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

    protected static function booted(): void
    {
        static::saving(function (MenuCategory $category): void {
            if (filled($category->slug)) {
                return;
            }

            $category->slug = Slugger::unique(
                match (mb_strtolower(trim((string) $category->name))) {
                    'чай' => 'tea',
                    'кофе' => 'coffee',
                    'лимонад' => 'lemonade',
                    default => (string) $category->name,
                },
                self::class,
                scope: ['type' => $category->type],
                ignoreId: $category->id,
                fallback: 'category',
            );
        });
    }
}
