<?php

namespace App\Models;

use App\Models\Concerns\HasI18n;
use App\Support\Money;
use App\Support\StoredUrl;
use Database\Factories\ModifierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'modifier_category_id',
    'name',
    'price',
    'image_url',
    'is_active',
    'sort_order',
    'i18n',
])]
class Modifier extends Model
{
    /** @use HasFactory<ModifierFactory> */
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
            'price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'i18n' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ModifierCategory::class, 'modifier_category_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'modifier_product')
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

    public function priceLabel(): string
    {
        return '+'.Money::format($this->price);
    }

    public function publicImageUrl(): ?string
    {
        return StoredUrl::public($this->image_url);
    }
}
