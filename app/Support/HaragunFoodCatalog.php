<?php

namespace App\Support;

use App\Enums\EstablishmentType;
use App\Enums\MenuCatalog;
use App\Enums\ProductKind;
use App\Models\Brand;
use App\Models\MenuCategory;
use App\Models\Product;
use Illuminate\Support\Collection;
use RuntimeException;

final class HaragunFoodCatalog
{
    /**
     * @return array{created: int, updated: int, skipped: list<string>}
     */
    public static function sync(?Brand $brand = null): array
    {
        $brand ??= self::arenaBrand();

        if ($brand === null) {
            throw new RuntimeException('Бренд ледовой арены не найден.');
        }

        $categories = MenuCategory::query()
            ->where('catalog', MenuCatalog::Snacks)
            ->active()
            ->get();

        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach (self::sections() as $section) {
            $category = self::findCategory($categories, $section['aliases']);

            if ($category === null) {
                $skipped[] = $section['aliases'][0];

                continue;
            }

            $sort = $section['sort_start'];

            foreach ($section['items'] as $item) {
                $wasCreated = self::upsert($brand, $category, $item['name'], $item['price'], $sort);
                $wasCreated ? $created++ : $updated++;
                $sort++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    private static function arenaBrand(): ?Brand
    {
        return Brand::query()
            ->where('type', EstablishmentType::IceArena)
            ->where(function ($query): void {
                $query->where('name', 'like', '%Haragun%')
                    ->orWhere('slug', 'like', '%haragun%');
            })
            ->orderBy('id')
            ->first()
            ?? Brand::query()->where('type', EstablishmentType::IceArena)->orderBy('id')->first();
    }

    /**
     * @param  Collection<int, MenuCategory>  $categories
     * @param  list<string>  $aliases
     */
    private static function findCategory(Collection $categories, array $aliases): ?MenuCategory
    {
        $normalizedAliases = collect($aliases)->map(fn (string $alias): string => self::normalize($alias));

        return $categories->first(
            fn (MenuCategory $category): bool => $normalizedAliases->contains(self::normalize($category->name)),
        );
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ё', '&'], ['е', 'и'], $value);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }

    private static function upsert(Brand $brand, MenuCategory $category, string $name, int $price, int $sortOrder): bool
    {
        $product = Product::query()
            ->where('brand_id', $brand->id)
            ->where('kind', ProductKind::Food)
            ->where('name', $name)
            ->first();

        $attributes = [
            'brand_id' => $brand->id,
            'establishment_id' => null,
            'menu_category_id' => $category->id,
            'kind' => ProductKind::Food,
            'name' => $name,
            'price' => $price,
            'weekend_price' => null,
            'food_section' => $category->name,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ];

        if ($product === null) {
            Product::query()->create($attributes);

            return true;
        }

        $product->forceFill($attributes)->save();

        return false;
    }

    /**
     * @return list<array{aliases: list<string>, sort_start: int, items: list<array{name: string, price: int}>}>
     */
    private static function sections(): array
    {
        return [
            [
                'aliases' => [
                    'Хот Дог & Бургеры',
                    'Хот дог & бургеры',
                    'Хот-доги и бургеры',
                    'Хот доги и бургеры',
                    'Hot Dogs & Burgers',
                    'Бургеры',
                ],
                'sort_start' => 10,
                'items' => [
                    ['name' => 'Хот дог с сосиской в булочке бриошь', 'price' => 3900],
                    ['name' => 'Смэш бургер с говяжьим беконом', 'price' => 7900],
                    ['name' => 'Бургер с рваной говядиной и соусом BBQ', 'price' => 7900],
                    ['name' => 'Бургер с хрустящей курочкой', 'price' => 5900],
                ],
            ],
            [
                'aliases' => [
                    'Горячие закуски',
                    'Hot Appetizers',
                    'Закуски',
                ],
                'sort_start' => 20,
                'items' => [
                    ['name' => 'Куриные наггетсы с кетчупом', 'price' => 2000],
                    ['name' => 'Куриные стрипсы', 'price' => 2500],
                    ['name' => 'Попкорн из креветок', 'price' => 3500],
                    ['name' => 'Батат фри', 'price' => 2500],
                    ['name' => 'Картофель фри', 'price' => 2000],
                    ['name' => 'Мак-н-чиз', 'price' => 3000],
                ],
            ],
            [
                'aliases' => [
                    'Соуса',
                    'Соусы',
                    'Sauces',
                ],
                'sort_start' => 30,
                'items' => [
                    ['name' => 'Сырный', 'price' => 500],
                    ['name' => 'Том ям (острый)', 'price' => 500],
                    ['name' => 'Кетчуп', 'price' => 400],
                    ['name' => 'Сладко-острый чили', 'price' => 500],
                ],
            ],
            [
                'aliases' => [
                    'Выпечка',
                    'Bakery',
                ],
                'sort_start' => 40,
                'items' => [
                    ['name' => 'Самса из песочного теста с грибами и трюфелем', 'price' => 1000],
                    ['name' => 'Самса из песочного теста с говядиной и соусом шрирача', 'price' => 1250],
                    ['name' => 'Самса из песочного теста с морепродуктами том-ям', 'price' => 1250],
                ],
            ],
            [
                'aliases' => [
                    'Суп',
                    'Супы',
                    'Soup',
                ],
                'sort_start' => 50,
                'items' => [
                    ['name' => 'Том ям с морепродуктами', 'price' => 4900],
                ],
            ],
            [
                'aliases' => [
                    'Десерт',
                    'Десерты',
                    'Dessert',
                ],
                'sort_start' => 60,
                'items' => [
                    ['name' => 'Шоколадный Брауни', 'price' => 4000],
                    ['name' => 'Чурос с горячим шоколадом', 'price' => 4000],
                ],
            ],
        ];
    }
}
