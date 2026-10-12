<?php

namespace App\Http\Controllers;

use App\Http\Resources\EstablishmentDetailResource;
use App\Http\Resources\FoodProductResource;
use App\Http\Resources\MenuCategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Services\EstablishmentBookingService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

#[Group('Arena', weight: 4)]
class EstablishmentController extends Controller
{
    public function __construct(private EstablishmentBookingService $booking) {}

    public function show(Request $request, Establishment $establishment): JsonResponse
    {
        abort_unless($establishment->is_active, 404);

        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'category' => ['nullable', 'string'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $latitude = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $longitude = isset($validated['lng']) ? (float) $validated['lng'] : null;

        $request->attributes->set('user_latitude', $latitude);
        $request->attributes->set('user_longitude', $longitude);

        $categories = $establishment->menuCategories()
            ->active()
            ->ordered()
            ->get();

        $selectedCategory = $this->resolveCategory($categories, $validated['category'] ?? null);

        $selectedDate = null;
        $dates = [];
        $slots = [];

        if ($selectedCategory?->requires_booking) {
            $dates = $this->booking->dates($establishment);
            $selectedDate = $this->booking->resolveDate($establishment, $validated['date'] ?? null);
            $slots = $this->booking->slots($establishment, $selectedDate);
            $request->attributes->set('booking_date', $selectedDate);
        }

        $products = collect();
        $sections = [];

        if ($selectedCategory) {
            if ($selectedCategory->isFoodCourt()) {
                $products = $selectedCategory->products()->food()->active()->ordered()->get();
                $sections = $products
                    ->groupBy(fn (Product $product) => $product->food_section ?: 'default')
                    ->map(fn ($items, $key) => [
                        'key' => $key,
                        'products' => FoodProductResource::collection($items->values()),
                    ])
                    ->values()
                    ->all();
            } else {
                $products = $selectedCategory->products()->tickets()->active()->ordered()->get();
            }
        }

        return response()->json([
            'establishment' => new EstablishmentDetailResource($establishment),
            'categories' => MenuCategoryResource::collection($categories),
            'selected_category' => $selectedCategory
                ? new MenuCategoryResource($selectedCategory)
                : null,
            'selected_date' => $selectedDate,
            'dates' => $dates,
            'slots' => $slots,
            'sections' => $sections,
            'products' => $selectedCategory?->isFoodCourt()
                ? FoodProductResource::collection($products)
                : ProductResource::collection($products),
        ]);
    }

    /**
     * @param  Collection<int, MenuCategory>  $categories
     */
    private function resolveCategory($categories, ?string $category): ?MenuCategory
    {
        if ($categories->isEmpty()) {
            return null;
        }

        if ($category === null) {
            return $categories->first();
        }

        if (in_array($category, ['snack', 'foodcourt'], true)) {
            return $categories->first(
                fn (MenuCategory $item) => in_array($item->slug, ['snack', 'foodcourt'], true)
            ) ?? $categories->first();
        }

        return $categories->first(
            fn (MenuCategory $item) => $item->slug === $category || (string) $item->id === $category
        ) ?? $categories->first();
    }
}
