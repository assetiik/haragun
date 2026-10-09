<?php

namespace App\Models;

use App\Models\Concerns\HasI18n;
use App\Support\Slugger;
use Database\Factories\ModifierCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'is_active',
    'sort_order',
    'i18n',
])]
class ModifierCategory extends Model
{
    /** @use HasFactory<ModifierCategoryFactory> */
    use HasFactory;

    use HasI18n;

    /**
     * @return list<string>
     */
    protected function translatableAttributes(): array
    {
        return ['name'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'i18n' => 'array',
        ];
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class);
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

    protected static function booted(): void
    {
        static::saving(function (ModifierCategory $category): void {
            if (filled($category->slug)) {
                return;
            }

            $category->slug = Slugger::unique(
                $category->stored('name'),
                self::class,
                [],
                $category->id,
                'modifier-category',
            );
        });
    }
}
