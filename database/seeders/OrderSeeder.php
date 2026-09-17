<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('phone', '77770000000')->first();
        $coffee = Establishment::query()->where('name', 'Кофейня «Москва»')->first();
        $arena = Establishment::query()->where('name', 'Ледовая арена Haragun')->first();

        if ($user === null || $coffee === null || $arena === null) {
            return;
        }

        $venueActive = Order::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'number' => 4821,
            ],
            [
                'establishment_id' => $coffee->id,
                'type' => OrderType::Venue,
                'status' => OrderStatus::Preparing,
                'code' => 'HRG-4821',
                'session_date' => Carbon::today()->toDateString(),
                'session_time' => '09:36:00',
                'session_ends_at' => null,
                'subtotal' => 8100,
                'discount_total' => 0,
                'total' => 8100,
                'items_count' => 5,
                'summary_title' => 'Капучино, чизкейк и сэндвич',
                'summary_short' => '4 напитка, 4 десерта и сэндвич',
                'payment_label' => 'Kaspi Gold · 4417',
                'points_to_earn' => 405,
                'prep_minutes' => 5,
                'paid_at' => Carbon::today()->setTime(9, 36),
                'accepted_at' => Carbon::today()->setTime(9, 36),
                'preparing_at' => Carbon::today()->setTime(9, 38),
                'ready_at' => null,
                'completed_at' => null,
            ],
        );

        $venueActive->items()->delete();
        OrderItem::query()->insert([
            [
                'order_id' => $venueActive->id,
                'product_id' => null,
                'kind' => ProductKind::Food->value,
                'name' => 'Капучино',
                'subtitle' => '250 мл · без сахара',
                'quantity' => 2,
                'unit_price' => 1200,
                'amount' => 2400,
                'sizes' => null,
                'icon_key' => 'coffee',
                'is_discount' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $venueActive->id,
                'product_id' => null,
                'kind' => ProductKind::Food->value,
                'name' => 'Чизкейк Нью-Йорк',
                'subtitle' => '1 шт · порция 140 г',
                'quantity' => 1,
                'unit_price' => 1900,
                'amount' => 1900,
                'sizes' => null,
                'icon_key' => 'cheesecake',
                'is_discount' => false,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $venueActive->id,
                'product_id' => null,
                'kind' => ProductKind::Food->value,
                'name' => 'Сэндвич с курицей',
                'subtitle' => 'на бриоши · 2 шт',
                'quantity' => 2,
                'unit_price' => 1900,
                'amount' => 3800,
                'sizes' => null,
                'icon_key' => 'sandwich',
                'is_discount' => false,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $venueHistory = Order::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'number' => 3902,
            ],
            [
                'establishment_id' => $coffee->id,
                'type' => OrderType::Venue,
                'status' => OrderStatus::Completed,
                'code' => 'HRG-3902',
                'session_date' => Carbon::parse('2026-03-02')->toDateString(),
                'session_time' => '12:00:00',
                'subtotal' => 2250,
                'discount_total' => 0,
                'total' => 2250,
                'items_count' => 2,
                'summary_title' => 'Латте, круассан',
                'summary_short' => 'Латте, круассан',
                'payment_label' => 'Kaspi Gold · 4417',
                'points_to_earn' => 112,
                'prep_minutes' => 5,
                'paid_at' => Carbon::parse('2026-03-02 12:00:00'),
                'accepted_at' => Carbon::parse('2026-03-02 12:00:00'),
                'preparing_at' => Carbon::parse('2026-03-02 12:01:00'),
                'ready_at' => Carbon::parse('2026-03-02 12:05:00'),
                'completed_at' => Carbon::parse('2026-03-02 12:10:00'),
            ],
        );

        $venueHistory->items()->delete();

        $ticketActive = Order::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'number' => 4822,
            ],
            [
                'establishment_id' => $arena->id,
                'type' => OrderType::Tickets,
                'status' => OrderStatus::Confirmed,
                'code' => 'HRG-4822',
                'session_date' => Carbon::today()->toDateString(),
                'session_time' => '16:00:00',
                'session_ends_at' => '17:00:00',
                'subtotal' => 12500,
                'discount_total' => 2500,
                'total' => 9560,
                'items_count' => 5,
                'summary_title' => 'Два билета, коньки и шлем',
                'summary_short' => '2 билета, коньки, шлем',
                'payment_label' => 'Kaspi Gold · 4417',
                'points_to_earn' => 480,
                'prep_minutes' => null,
                'paid_at' => now()->subHour(),
                'accepted_at' => now()->subHour(),
                'preparing_at' => null,
                'ready_at' => null,
                'completed_at' => null,
            ],
        );

        $ticketActive->items()->delete();
        OrderItem::query()->insert([
            [
                'order_id' => $ticketActive->id,
                'product_id' => null,
                'kind' => ProductKind::Ticket->value,
                'name' => 'Входной билет · взрослый',
                'subtitle' => 'Сеанс 60 минут · от 13 лет',
                'quantity' => 2,
                'unit_price' => 2500,
                'amount' => 5000,
                'sizes' => null,
                'icon_key' => 'ticket',
                'is_discount' => false,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $ticketActive->id,
                'product_id' => null,
                'kind' => ProductKind::RentalSkates->value,
                'name' => 'Аренда коньков',
                'subtitle' => 'Размеры 38 и 41 · пара',
                'quantity' => 2,
                'unit_price' => 2500,
                'amount' => 5000,
                'sizes' => json_encode([38, 41]),
                'icon_key' => 'skates',
                'is_discount' => false,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $ticketActive->id,
                'product_id' => null,
                'kind' => ProductKind::RentalHelmet->value,
                'name' => 'Шлем',
                'subtitle' => 'Универсальный размер',
                'quantity' => 1,
                'unit_price' => 2500,
                'amount' => 2500,
                'sizes' => null,
                'icon_key' => 'helmet',
                'is_discount' => false,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $ticketActive->id,
                'product_id' => null,
                'kind' => 'discount',
                'name' => 'Скидка на коньки, будни',
                'subtitle' => null,
                'quantity' => 1,
                'unit_price' => -2500,
                'amount' => -2500,
                'sizes' => null,
                'icon_key' => null,
                'is_discount' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Order::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'number' => 4100,
            ],
            [
                'establishment_id' => $arena->id,
                'type' => OrderType::Tickets,
                'status' => OrderStatus::Completed,
                'code' => 'HRG-4100',
                'session_date' => Carbon::parse('2026-02-24')->toDateString(),
                'session_time' => '18:00:00',
                'session_ends_at' => '19:00:00',
                'subtotal' => 9000,
                'discount_total' => 0,
                'total' => 9000,
                'items_count' => 2,
                'summary_title' => '1 взрослый, 1 детский',
                'summary_short' => '1 взрослый, 1 детский',
                'payment_label' => 'Kaspi Gold · 4417',
                'points_to_earn' => 450,
                'paid_at' => Carbon::parse('2026-02-24 17:00:00'),
                'accepted_at' => Carbon::parse('2026-02-24 17:00:00'),
                'completed_at' => Carbon::parse('2026-02-24 19:00:00'),
            ],
        );
    }
}
