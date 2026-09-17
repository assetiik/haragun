<?php

namespace Database\Seeders;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\EstablishmentUnavailableSlot;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Models\SkateSize;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EstablishmentCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $arena = Establishment::query()
            ->where('name', 'Ледовая арена Haragun')
            ->first();

        if ($arena === null) {
            return;
        }

        $arena->update([
            'type' => EstablishmentType::IceArena,
            'parking_is_free' => true,
            'session_duration_minutes' => 60,
            'slot_interval_minutes' => 120,
            'ice_temperature' => -9,
            'ice_note' => 'лёд свежий',
            'header_color' => '#6B4EFF',
            'booking_days_ahead' => 5,
            'opens_at' => '10:00:00',
            'closes_at' => '22:00:00',
        ]);

        $ice = MenuCategory::query()->updateOrCreate(
            [
                'type' => EstablishmentType::IceArena,
                'slug' => 'ice_rental',
            ],
            [
                'establishment_id' => $arena->id,
                'name' => 'Лёд и аренда',
                'section_title' => 'Входной билет',
                'section_subtitle' => 'Сеанс 60 минут · забронируйте дату и время',
                'requires_booking' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $foodcourt = MenuCategory::query()->updateOrCreate(
            [
                'type' => EstablishmentType::IceArena,
                'slug' => 'foodcourt',
            ],
            [
                'establishment_id' => $arena->id,
                'name' => 'Фудкорт',
                'section_title' => 'Фудкорт',
                'section_subtitle' => 'Перекус',
                'header_title' => 'Фудкорт',
                'header_subtitle' => 'Заберёте у стойки — попадёт в тот же чек',
                'header_color' => '#1B2A4A',
                'prep_minutes' => 5,
                'requires_booking' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        // Remove old snack category if it exists from previous seeds.
        MenuCategory::query()
            ->where('establishment_id', $arena->id)
            ->where('slug', 'snack')
            ->delete();

        Product::query()->updateOrCreate(
            [
                'establishment_id' => $arena->id,
                'menu_category_id' => $ice->id,
                'name' => 'Взрослый',
            ],
            [
                'kind' => ProductKind::Ticket,
                'price' => 2500,
                'meta' => 'от 13 лет',
                'age_from' => 13,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Product::query()->updateOrCreate(
            [
                'establishment_id' => $arena->id,
                'menu_category_id' => $ice->id,
                'name' => 'Детский',
            ],
            [
                'kind' => ProductKind::Ticket,
                'price' => 1500,
                'meta' => 'до 12 лет',
                'age_from' => null,
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        $skates = Product::query()->updateOrCreate(
            [
                'establishment_id' => $arena->id,
                'menu_category_id' => $ice->id,
                'name' => 'Коньки',
            ],
            [
                'kind' => ProductKind::RentalSkates,
                'requires_size' => true,
                'size_min' => 35,
                'size_max' => 46,
                'price' => 2500,
                'meta' => 'за пару',
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        foreach (range(35, 46) as $size) {
            SkateSize::query()->updateOrCreate(
                [
                    'product_id' => $skates->id,
                    'size' => $size,
                ],
                [
                    'is_available' => $size !== 46,
                ],
            );
        }

        Product::query()->updateOrCreate(
            [
                'establishment_id' => $arena->id,
                'menu_category_id' => $ice->id,
                'name' => 'Шлем',
            ],
            [
                'kind' => ProductKind::RentalHelmet,
                'requires_size' => false,
                'price' => 2500,
                'meta' => 'универсальный размер',
                'is_active' => true,
                'sort_order' => 11,
            ],
        );

        $foodItems = [
            [
                'name' => 'Хот-дог',
                'price' => 1500,
                'meta' => null,
                'food_section' => 'Перекус',
                'icon_key' => 'hotdog',
                'sort_order' => 1,
            ],
            [
                'name' => 'Пончик',
                'price' => 800,
                'meta' => null,
                'food_section' => 'Перекус',
                'icon_key' => 'donut',
                'sort_order' => 2,
            ],
        ];

        foreach ($foodItems as $item) {
            Product::query()->updateOrCreate(
                [
                    'establishment_id' => $arena->id,
                    'menu_category_id' => $foodcourt->id,
                    'name' => $item['name'],
                ],
                [
                    'kind' => ProductKind::Food,
                    'price' => $item['price'],
                    'meta' => $item['meta'],
                    'food_section' => $item['food_section'],
                    'icon_key' => $item['icon_key'],
                    'is_active' => true,
                    'sort_order' => $item['sort_order'],
                ],
            );
        }

        // Clean obsolete food items from early seeds.
        Product::query()
            ->where('establishment_id', $arena->id)
            ->where('menu_category_id', $foodcourt->id)
            ->where('kind', ProductKind::Food)
            ->whereNotIn('name', collect($foodItems)->pluck('name'))
            ->delete();

        EstablishmentUnavailableSlot::query()->updateOrCreate(
            [
                'establishment_id' => $arena->id,
                'date' => Carbon::today()->toDateString(),
                'time' => '10:00:00',
            ],
        );
    }
}
