<?php

namespace App\Models;

use App\Enums\BannerPlacement;
use App\Models\Concerns\HasI18n;
use App\Support\StoredUrl;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'city_id',
    'placement',
    'title',
    'subtitle',
    'button_label',
    'background_color',
    'image_url',
    'is_active',
    'sort_order',
    'i18n',
])]
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    use HasI18n;

    /**
     * @return list<string>
     */
    protected function translatableAttributes(): array
    {
        return ['title', 'subtitle', 'button_label'];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'placement' => 'hero',
        'background_color' => '#6B4EFF',
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placement' => BannerPlacement::class,
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
    protected function hero(Builder $query): void
    {
        $query->where('placement', BannerPlacement::Hero);
    }

    #[Scope]
    protected function promo(Builder $query): void
    {
        $query->where('placement', BannerPlacement::Promo);
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
