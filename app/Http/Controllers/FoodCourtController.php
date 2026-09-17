<?php

namespace App\Http\Controllers;

use App\Http\Resources\FoodProductResource;
use App\Models\Establishment;
use App\Models\MenuCategory;
use App\Models\Product;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $sessionDate = Carbon::parse($date);

        $products = Product::query()
            ->where('menu_category_id', $category->id)
            ->food()
            ->active()
            ->ordered()
            ->get();

        $sections = $products
            ->groupBy(fn (Product $product) => $product->food_section ?: 'Меню')
            ->map(fn ($items, $title) => [
                'title' => $title,
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
                'title' => $category->header_title ?: 'Фудкорт',
                'subtitle' => $category->header_subtitle
                    ?: 'Заберёте у стойки — попадёт в тот же чек',
                'color' => $category->header_color ?: '#1B2A4A',
            ],
            'badges' => [
                [
                    'type' => 'status',
                    'label' => $establishment->statusLabel(),
                    'is_open' => $establishment->isOpenAt(),
                ],
                [
                    'type' => 'prep',
                    'label' => "Готовим за {$prepMinutes} минут",
                    'prep_minutes' => $prepMinutes,
                ],
            ],
            'session' => [
                'date' => $date,
                'time' => $time,
                'short_label' => $this->shortSessionLabel($sessionDate, $time),
            ],
            'sections' => $sections,
            'products' => FoodProductResource::collection($products),
        ]);
    }

    private function shortSessionLabel(Carbon $date, string $time): string
    {
        $weekdays = [0 => 'вс', 1 => 'пн', 2 => 'вт', 3 => 'ср', 4 => 'чт', 5 => 'пт', 6 => 'сб'];

        return "{$weekdays[$date->dayOfWeek]}, {$date->day} · {$time}";
    }
}
