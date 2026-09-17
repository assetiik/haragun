<?php

namespace App\Http\Resources;

use App\Models\Modifier;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin Product
 */
class CoffeeProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $defaultSize = $this->defaultSize();
        $modifiers = $this->modifiers
            ->where('is_active', true)
            ->values();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'meta' => $this->meta,
            'subtitle' => collect([
                $defaultSize?->label ?? $this->meta,
                $this->description,
            ])->filter()->implode(' · '),
            'image_url' => $this->publicImageUrl(),
            'background_color' => $this->background_color ?? '#C8A45D',
            'base_price' => $this->price,
            'base_price_label' => $this->priceLabel(),
            'default_size_id' => $defaultSize?->id,
            'sizes' => $this->sizes->map(fn ($size) => [
                'id' => $size->id,
                'code' => $size->code,
                'label' => $size->label,
                'volume_ml' => $size->volume_ml,
                'price' => $size->price,
                'price_label' => Money::format($size->price),
                'is_default' => $size->is_default,
            ])->values(),
            'modifiers' => $this->serializeModifiers($modifiers),
            'modifier_groups' => $modifiers->isEmpty() ? [] : [
                [
                    'id' => null,
                    'name' => 'Добавки',
                    'slug' => 'extras',
                    'modifiers' => $this->serializeModifiers($modifiers),
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, Modifier>  $modifiers
     * @return list<array{id: int, name: string, price: int, price_label: string, image_url: ?string}>
     */
    private function serializeModifiers(Collection $modifiers): array
    {
        return $modifiers
            ->map(fn (Modifier $modifier): array => [
                'id' => $modifier->id,
                'name' => $modifier->name,
                'price' => $modifier->price,
                'price_label' => $modifier->priceLabel(),
                'image_url' => $modifier->publicImageUrl(),
            ])
            ->values()
            ->all();
    }
}
