<?php

namespace App\Http\Controllers;

use App\Enums\MenuCatalog;
use App\Http\Resources\CoffeeProductDetailResource;
use App\Http\Resources\FoodProductResource;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

#[Group('Arena', weight: 4)]
class FoodCourtController extends Controller
{
    public function show(Request $request, Establishment $establishment): JsonResponse
    {
        abort_unless($establishment->is_active, 404);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
        ]);

        $category = MenuCategory::query()
            ->where('establishment_id', $establishment->id)
            ->whereIn('slug', ['foodcourt', 'snack'])
            ->active()
            ->ordered()
            ->first();

        abort_if($category === null, 404, 'Фудкорт недоступен');

        $date = $validated['date'] ?? now()->toDateString();
        $time = $validated['time'] ?? '16:00';

        $legacyProducts = Product::query()
            ->with('menuCategory')
            ->where('menu_category_id', $category->id)
            ->food()
            ->active()
            ->ordered()
            ->get();

        $catalogProducts = Product::query()
            ->with('menuCategory')
            ->availableAt($establishment)
            ->food()
            ->active()
            ->whereHas(
                'menuCategory',
                fn ($query) => $query->where('catalog', MenuCatalog::Snacks),
            )
            ->ordered()
            ->get();

        /** @var Collection<int, Product> $products */
        $products = $legacyProducts
            ->concat($catalogProducts)
            ->unique('id')
            ->sortBy('sort_order')
            ->values();

        $sections = $products
            ->groupBy(fn (Product $product): string => $this->sectionKey($product))
            ->map(fn ($items, $key) => [
                'key' => $key,
                'products' => FoodProductResource::collection($items->values()),
            ])
            ->values();

        $prepMinutes = $category->prep_minutes ?? 5;

        return response()->json([
            'establishment' => [
                'id' => $establishment->id,
                'name' => $establishment->name,
            ],
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'header' => [
                'title' => $category->header_title,
                'subtitle' => $category->header_subtitle,
                'color' => $category->header_color,
            ],
            'is_open' => $establishment->isOpenAt(),
            'prep_minutes' => $prepMinutes,
            'session' => [
                'date' => $date,
                'time' => $time,
            ],
            'sections' => $sections,
            'products' => FoodProductResource::collection($products),
        ]);
    }

    public function product(Establishment $establishment, Product $product): CoffeeProductDetailResource
    {
        abort_unless($establishment->is_active, 404);

        $product->loadMissing('menuCategory');

        abort_unless(
            $product->is_active && $this->belongsToFoodCourt($establishment, $product),
            404
        );

        return new CoffeeProductDetailResource($product);
    }

    private function sectionKey(Product $product): string
    {
        if ($product->menuCategory?->isArenaFoodCategory()) {
            return $product->menuCategory->name;
        }

        return $product->food_section ?: 'default';
    }

    private function belongsToFoodCourt(Establishment $establishment, Product $product): bool
    {
        if ($product->establishment_id === $establishment->id && $product->menuCategory?->isFoodCourt()) {
            return true;
        }

        return $product->belongsToVenue($establishment)
            && $product->menuCategory?->isArenaFoodCategory();
    }
}
