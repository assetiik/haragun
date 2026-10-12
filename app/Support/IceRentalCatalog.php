<?php

namespace App\Support;

use App\Enums\ProductKind;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\SkateSize;

class IceRentalCatalog
{
    public static function sync(MenuCategory $category): void
    {
        $child = self::upsert($category, ['Детские коньки (бренд GRAF)'], [
            'kind' => ProductKind::RentalSkates,
            'name' => 'Детские коньки (бренд GRAF)',
            'price' => 2000,
            'weekend_price' => 2000,
            'meta' => '1 сеанс',
            'description' => 'Размеры от 25 до 35, профессиональная заточка, регулярная дезинфекция',
            'requires_size' => true,
            'sort_order' => 10,
            'is_active' => true,
        ]);
        self::syncSizes($child, 25, 35);

        $adult = self::upsert($category, ['Взрослые коньки', 'Коньки'], [
            'kind' => ProductKind::RentalSkates,
            'name' => 'Взрослые коньки',
            'price' => 3000,
            'weekend_price' => 3000,
            'meta' => '1 сеанс',
            'description' => 'Размеры от 36 до 47, бренд GRAF, комфортные анатомические стельки',
            'requires_size' => true,
            'sort_order' => 11,
            'is_active' => true,
        ], ProductKind::RentalSkates);
        self::syncSizes($adult, 36, 47);

        self::upsert($category, ['Шлем / защита', 'Шлем'], [
            'kind' => ProductKind::RentalHelmet,
            'name' => 'Шлем / защита',
            'price' => 1500,
            'weekend_price' => 1500,
            'meta' => '1 сеанс',
            'description' => 'Защитные элементы (шлем, наколенники, налокотники) выдаются по запросу',
            'requires_size' => false,
            'size_min' => null,
            'size_max' => null,
            'sort_order' => 12,
            'is_active' => true,
        ], ProductKind::RentalHelmet);

        self::upsert($category, ['Прокат опорной фигуры «помогайка»'], [
            'kind' => ProductKind::RentalSupport,
            'name' => 'Прокат опорной фигуры «помогайка»',
            'price' => 2000,
            'weekend_price' => 2000,
            'meta' => '1 сеанс',
            'description' => 'Пингвин, тюлень, поддерживающая фигурка для начинающих. Услуга предназначена для начинающих и детей — используется для поддержки равновесия и обучения безопасному катанию на льду.',
            'requires_size' => false,
            'size_min' => null,
            'size_max' => null,
            'sort_order' => 13,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<string>  $names
     * @param  array<string, mixed>  $attributes
     */
    private static function upsert(MenuCategory $category, array $names, array $attributes, ?ProductKind $kind = null): Product
    {
        $query = Product::query()
            ->where('establishment_id', $category->establishment_id)
            ->where('menu_category_id', $category->id)
            ->whereIn('name', $names);

        if ($kind !== null) {
            $query->where('kind', $kind);
        }

        $product = $query->orderBy('id')->first() ?? new Product([
            'establishment_id' => $category->establishment_id,
            'menu_category_id' => $category->id,
        ]);

        $product->fill($attributes);
        $product->save();

        return $product;
    }

    private static function syncSizes(Product $product, int $from, int $to): void
    {
        $product->skateSizes()->whereNotBetween('size', [$from, $to])->delete();

        foreach (range($from, $to) as $size) {
            SkateSize::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'size' => $size,
                ],
                [
                    'is_available' => true,
                ],
            );
        }

        $product->update([
            'requires_size' => true,
            'size_min' => $from,
            'size_max' => $to,
        ]);
    }
}
