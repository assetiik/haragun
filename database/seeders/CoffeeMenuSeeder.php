<?php

namespace Database\Seeders;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

class CoffeeMenuSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Establishment::query()
            ->where('is_active', true)
            ->whereIn('type', [
                EstablishmentType::Coffee,
                EstablishmentType::IceArena,
            ])
            ->get();

        $categoryModels = $this->sharedCategories();
        $modifierIdsByGroup = $this->seedModifiers();

        foreach ($branches as $branch) {
            $this->seedBranch($branch, $categoryModels, $modifierIdsByGroup);
        }
    }

    /**
     * @return array<string, MenuCategory>
     */
    private function sharedCategories(): array
    {
        $categories = $this->categoryDefinitions();
        $categoryModels = [];

        foreach ($categories as $slug => $data) {
            $categoryModels[$slug] = MenuCategory::query()->updateOrCreate(
                [
                    'type' => EstablishmentType::Coffee,
                    'slug' => $slug,
                ],
                [
                    'establishment_id' => null,
                    'name' => $data['name'],
                    'section_title' => $data['name'],
                    'section_subtitle' => null,
                    'requires_booking' => false,
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                ],
            );
        }

        return $categoryModels;
    }

    /**
     * @return array<string, array{name: string, sort_order: int}>
     */
    private function categoryDefinitions(): array
    {
        return [
            'tea' => ['name' => 'Чай', 'sort_order' => 1],
            'coffee' => ['name' => 'Кофе', 'sort_order' => 2],
            'lemonade' => ['name' => 'Лимонад', 'sort_order' => 3],
        ];
    }

    /**
     * @param  array<string, MenuCategory>  $categoryModels
     * @param  array<string, list<int>>  $modifierIdsByGroup
     */
    private function seedBranch(Establishment $branch, array $categoryModels, array $modifierIdsByGroup): void
    {
        MenuCategory::query()
            ->where('establishment_id', $branch->id)
            ->where('slug', 'menu')
            ->get()
            ->each(function (MenuCategory $category): void {
                Product::query()->where('menu_category_id', $category->id)->delete();
                $category->delete();
            });

        $categories = $this->categoryDefinitions();

        $products = [
            [
                'name' => 'Латте',
                'category' => 'coffee',
                'price' => 1690,
                'meta' => '350 мл',
                'description' => 'эспрессо и молоко, мягкая пенка',
                'image_url' => 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=800',
                'background_color' => '#C8A45D',
                'is_popular' => true,
                'is_featured' => true,
                'sort_order' => 1,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 1490, 'is_default' => false],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 1690, 'is_default' => true],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 1890, 'is_default' => false],
                ],
                'extras' => ['toppings', 'milk', 'syrups'],
            ],
            [
                'name' => 'Капучино',
                'category' => 'coffee',
                'price' => 1590,
                'meta' => '350 мл',
                'description' => 'эспрессо и молоко, плотная пенка',
                'image_url' => 'https://images.unsplash.com/photo-1572442388796-11668a67e53d?w=800',
                'background_color' => '#B8956A',
                'is_popular' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 1390, 'is_default' => false],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 1590, 'is_default' => true],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 1790, 'is_default' => false],
                ],
                'extras' => ['toppings', 'milk', 'syrups'],
            ],
            [
                'name' => 'Айс американо',
                'category' => 'coffee',
                'price' => 1590,
                'meta' => '400 мл',
                'description' => 'эспрессо со льдом и водой',
                'image_url' => 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?w=800',
                'background_color' => '#8B7355',
                'is_popular' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 1390, 'is_default' => false],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 1590, 'is_default' => true],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 1790, 'is_default' => false],
                ],
                'extras' => ['syrups'],
            ],
            [
                'name' => 'Бамбл кофе',
                'category' => 'coffee',
                'price' => 1590,
                'meta' => '400 мл',
                'description' => 'эспрессо, апельсиновый сок и лёд',
                'image_url' => 'https://images.unsplash.com/photo-1517487881594-2787fef5ebf7?w=800',
                'background_color' => '#E8A838',
                'is_popular' => true,
                'is_featured' => false,
                'sort_order' => 4,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 1390, 'is_default' => false],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 1590, 'is_default' => true],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 1790, 'is_default' => false],
                ],
                'extras' => ['syrups'],
            ],
            [
                'name' => 'Горячий шоколад',
                'category' => 'coffee',
                'price' => 1539,
                'meta' => '300 мл',
                'description' => 'густой шоколад с взбитыми сливками',
                'image_url' => 'https://images.unsplash.com/photo-1542990253-0d0f5be5f0ed?w=800',
                'background_color' => '#6B4423',
                'is_popular' => true,
                'is_featured' => false,
                'sort_order' => 5,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 1539, 'is_default' => true],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 1739, 'is_default' => false],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 1939, 'is_default' => false],
                ],
                'extras' => ['toppings', 'milk'],
            ],
            [
                'name' => 'Эспрессо',
                'category' => 'coffee',
                'price' => 990,
                'meta' => '30 мл',
                'description' => 'классический двойной эспрессо',
                'image_url' => 'https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?w=800',
                'background_color' => '#4A3728',
                'is_popular' => false,
                'is_featured' => false,
                'sort_order' => 6,
                'sizes' => [
                    ['code' => 'S', 'label' => '30 мл', 'volume_ml' => 30, 'price' => 990, 'is_default' => true],
                    ['code' => 'M', 'label' => '60 мл', 'volume_ml' => 60, 'price' => 1290, 'is_default' => false],
                ],
                'extras' => [],
            ],
            [
                'name' => 'Чай чёрный',
                'category' => 'tea',
                'price' => 890,
                'meta' => '400 мл',
                'description' => 'крепкий листовой чай',
                'image_url' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?w=800',
                'background_color' => '#8B4513',
                'is_popular' => false,
                'is_featured' => false,
                'sort_order' => 1,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 790, 'is_default' => false],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 890, 'is_default' => true],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 990, 'is_default' => false],
                ],
                'extras' => ['syrups'],
            ],
            [
                'name' => 'Чай зелёный',
                'category' => 'tea',
                'price' => 890,
                'meta' => '400 мл',
                'description' => 'лёгкий зелёный чай',
                'image_url' => 'https://images.unsplash.com/photo-1556679343-c7306c197cfd?w=800',
                'background_color' => '#6B8E23',
                'is_popular' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 790, 'is_default' => false],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 890, 'is_default' => true],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 990, 'is_default' => false],
                ],
                'extras' => ['syrups'],
            ],
            [
                'name' => 'Масала чай',
                'category' => 'tea',
                'price' => 1290,
                'meta' => '350 мл',
                'description' => 'пряный чай с молоком и специями',
                'image_url' => 'https://images.unsplash.com/photo-1571934811356-5cc061b6821f?w=800',
                'background_color' => '#D2691E',
                'is_popular' => false,
                'is_featured' => false,
                'sort_order' => 3,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 1190, 'is_default' => false],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 1290, 'is_default' => true],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 1490, 'is_default' => false],
                ],
                'extras' => ['milk'],
            ],
            [
                'name' => 'Цитрусовый микс',
                'category' => 'lemonade',
                'price' => 1590,
                'meta' => '400 мл',
                'description' => 'апельсин, лимон и мята',
                'image_url' => 'https://images.unsplash.com/photo-1523677011780-c68ee1712663?w=800',
                'background_color' => '#F4C430',
                'is_popular' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 1390, 'is_default' => false],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 1590, 'is_default' => true],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 1790, 'is_default' => false],
                ],
                'extras' => [],
            ],
            [
                'name' => 'Клубничный лимонад',
                'category' => 'lemonade',
                'price' => 1490,
                'meta' => '400 мл',
                'description' => 'клубника, лайм и газировка',
                'image_url' => 'https://images.unsplash.com/photo-1497534446932-c925b458314e?w=800',
                'background_color' => '#E85A71',
                'is_popular' => false,
                'is_featured' => false,
                'sort_order' => 2,
                'sizes' => [
                    ['code' => 'S', 'label' => '300 мл', 'volume_ml' => 300, 'price' => 1290, 'is_default' => false],
                    ['code' => 'M', 'label' => '400 мл', 'volume_ml' => 400, 'price' => 1490, 'is_default' => true],
                    ['code' => 'L', 'label' => '500 мл', 'volume_ml' => 500, 'price' => 1690, 'is_default' => false],
                ],
                'extras' => [],
            ],
        ];

        $keepNames = collect($products)->pluck('name');

        Product::query()
            ->where('establishment_id', $branch->id)
            ->where('kind', ProductKind::Food)
            ->whereNotIn('name', $keepNames)
            ->whereHas(
                'menuCategory',
                fn (Builder $query): Builder => $query->where('type', EstablishmentType::Coffee),
            )
            ->delete();

        foreach ($products as $item) {
            $product = Product::query()->updateOrCreate(
                [
                    'establishment_id' => $branch->id,
                    'name' => $item['name'],
                ],
                [
                    'menu_category_id' => $categoryModels[$item['category']]->id,
                    'kind' => ProductKind::Food,
                    'price' => $item['price'],
                    'meta' => $item['meta'],
                    'description' => $item['description'],
                    'image_url' => $item['image_url'],
                    'background_color' => $item['background_color'],
                    'is_popular' => $item['is_popular'],
                    'is_featured' => $item['is_featured'],
                    'food_section' => $categories[$item['category']]['name'],
                    'icon_key' => 'drink',
                    'is_active' => true,
                    'sort_order' => $item['sort_order'],
                ],
            );

            foreach ($item['sizes'] as $index => $size) {
                ProductSize::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'code' => $size['code'],
                    ],
                    [
                        'label' => $size['label'],
                        'volume_ml' => $size['volume_ml'],
                        'price' => $size['price'],
                        'is_default' => $size['is_default'],
                        'sort_order' => $index + 1,
                    ],
                );
            }

            ProductSize::query()
                ->where('product_id', $product->id)
                ->whereNotIn('code', collect($item['sizes'])->pluck('code'))
                ->delete();

            $sync = [];
            $sortOrder = 1;
            foreach ($item['extras'] as $groupSlug) {
                foreach ($modifierIdsByGroup[$groupSlug] as $modifierId) {
                    $sync[$modifierId] = ['sort_order' => $sortOrder];
                    $sortOrder++;
                }
            }
            $product->modifiers()->sync($sync);
        }
    }

    /**
     * @return array<string, list<int>>
     */
    private function seedModifiers(): array
    {
        $definitions = [
            'toppings' => [
                ['name' => 'Карамель', 'price' => 1250, 'image_url' => 'https://images.unsplash.com/photo-1587017539504-67cfbddac569?w=400'],
                ['name' => 'Шоколад', 'price' => 1250, 'image_url' => 'https://images.unsplash.com/photo-1511381939415-e44015466834?w=400'],
                ['name' => 'Солёная карамель', 'price' => 1250, 'image_url' => 'https://images.unsplash.com/photo-1481391319762-47dff72954d9?w=400'],
            ],
            'milk' => [
                ['name' => 'Овсяное', 'price' => 350, 'image_url' => 'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=400'],
                ['name' => 'Миндальное', 'price' => 350, 'image_url' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=400'],
                ['name' => 'Кокосовое', 'price' => 350, 'image_url' => 'https://images.unsplash.com/photo-1628088062854-d1870b4553da?w=400'],
            ],
            'syrups' => [
                ['name' => 'Ваниль', 'price' => 250, 'image_url' => 'https://images.unsplash.com/photo-1599599810694-b5ac4dd21054?w=400'],
                ['name' => 'Карамель', 'price' => 250, 'image_url' => 'https://images.unsplash.com/photo-1571115177098-24ec42ed204d?w=400'],
                ['name' => 'Лесной орех', 'price' => 250, 'image_url' => 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=400'],
            ],
        ];

        $modifierIdsByGroup = [];
        $sortOrder = 1;

        foreach ($definitions as $slug => $items) {
            $ids = [];

            foreach ($items as $item) {
                $modifier = Modifier::query()->updateOrCreate(
                    [
                        'name' => $item['name'],
                        'price' => $item['price'],
                    ],
                    [
                        'image_url' => $item['image_url'],
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                    ],
                );
                $ids[] = $modifier->id;
                $sortOrder++;
            }

            $modifierIdsByGroup[$slug] = $ids;
        }

        return $modifierIdsByGroup;
    }
}
