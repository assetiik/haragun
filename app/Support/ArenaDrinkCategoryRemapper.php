<?php

namespace App\Support;

use App\Enums\EstablishmentType;
use App\Enums\MenuCatalog;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\MenuCategory;
use App\Models\Product;

final class ArenaDrinkCategoryRemapper
{
    /**
     * Move ice-arena brand drinks from legacy coffee categories onto
     * matching Haragun "Категории блюд" rows (same slug).
     */
    public static function remap(): int
    {
        $arenaBrandIds = Brand::query()
            ->where('type', EstablishmentType::IceArena)
            ->pluck('id');

        if ($arenaBrandIds->isEmpty()) {
            return 0;
        }

        $targets = MenuCategory::query()
            ->where('catalog', MenuCatalog::Drinks)
            ->orderBy('id')
            ->get()
            ->keyBy('slug');

        if ($targets->isEmpty()) {
            return 0;
        }

        $moved = 0;

        foreach ($targets as $slug => $target) {
            $legacyIds = MenuCategory::query()
                ->where('type', EstablishmentType::Coffee)
                ->where('slug', $slug)
                ->pluck('id');

            if ($legacyIds->isEmpty()) {
                continue;
            }

            $moved += Product::query()
                ->whereIn('brand_id', $arenaBrandIds)
                ->where('kind', ProductKind::Food)
                ->whereIn('menu_category_id', $legacyIds)
                ->update(['menu_category_id' => $target->id]);
        }

        return $moved;
    }
}
