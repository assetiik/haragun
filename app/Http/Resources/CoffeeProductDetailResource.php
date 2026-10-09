<?php

namespace App\Http\Resources;

use App\Models\Modifier;
use App\Models\Product;
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

        $isFoodCourt = $this->menuCategory?->isFoodCourt() ?? false;

        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'name' => $this->name,
            'description' => $this->description,
            'meta' => $this->meta,
            'image_url' => $this->publicImageUrl(),
            'background_color' => $this->background_color ?? '#C8A45D',
            'selection_mode' => $isFoodCourt ? 'quantity' : 'coffee',
            'base_price' => $this->price,
            'default_size_id' => $isFoodCourt ? null : $defaultSize?->id,
            'sizes' => $isFoodCourt ? [] : $this->sizes->map(fn ($size) => [
                'id' => $size->id,
                'code' => $size->code,
                'label' => $size->label,
                'volume_ml' => $size->volume_ml,
                'price' => $size->price,
                'is_default' => $size->is_default,
            ])->values(),
            'modifiers' => $isFoodCourt ? [] : $this->serializeModifiers($modifiers),
            'modifier_groups' => $isFoodCourt ? [] : $this->serializeModifierGroups($modifiers),
        ];
    }

    /**
     * @param  Collection<int, Modifier>  $modifiers
     * @return list<array{id: ?int, name: ?string, slug: string, modifiers: list<array{id: int, name: string, price: int, image_url: ?string}>}>
     */
    private function serializeModifierGroups(Collection $modifiers): array
    {
        if ($modifiers->isEmpty()) {
            return [];
        }

        return $modifiers
            ->groupBy(fn (Modifier $modifier): string => (string) ($modifier->modifier_category_id ?? 'none'))
            ->map(function (Collection $groupModifiers): array {
                /** @var Modifier $first */
                $first = $groupModifiers->first();
                $category = $first->category;

                return [
                    'id' => $category?->id,
                    'name' => $category?->name,
                    'slug' => $category?->slug ?? 'extras',
                    'sort_order' => $category?->sort_order ?? PHP_INT_MAX,
                    'modifiers' => $this->serializeModifiers(
                        $groupModifiers->sortBy([
                            ['sort_order', 'asc'],
                            ['id', 'asc'],
                        ])->values()
                    ),
                ];
            })
            ->sortBy('sort_order')
            ->map(fn (array $group): array => [
                'id' => $group['id'],
                'name' => $group['name'],
                'slug' => $group['slug'],
                'modifiers' => $group['modifiers'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Modifier>  $modifiers
     * @return list<array{id: int, name: string, price: int, image_url: ?string}>
     */
    private function serializeModifiers(Collection $modifiers): array
    {
        return $modifiers
            ->map(fn (Modifier $modifier): array => [
                'id' => $modifier->id,
                'name' => $modifier->name,
                'price' => $modifier->price,
                'image_url' => $modifier->publicImageUrl(),
            ])
            ->values()
            ->all();
    }
}
