<?php

namespace App\Models;

use App\Models\Concerns\HasI18n;
use App\Support\StoredUrl;
use Database\Factories\PromoTileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'city_id',
    'title',
    'image_url',
    'is_active',
    'sort_order',
    'i18n',
])]
class PromoTile extends Model
{
    /** @use HasFactory<PromoTileFactory> */
    use HasFactory;

    use HasI18n;

    /**
     * @return list<string>
     */
    protected function translatableAttributes(): array
    {
        return ['title'];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

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

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function publicImageUrl(): ?string
    {
        return StoredUrl::public($this->image_url);
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
    protected function forCity(Builder $query, ?int $cityId): void
    {
        $query->where(function (Builder $builder) use ($cityId): void {
            $builder->whereNull('city_id');

            if ($cityId !== null) {
                $builder->orWhere('city_id', $cityId);
            }
        });
    }
}
