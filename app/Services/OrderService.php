<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ProductKind;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(private OrderQuoteService $quotes) {}

    /**
     * @param  array{
     *     establishment_id: int,
     *     date: string,
     *     time: string,
     *     items: list<array{product_id: int, quantity?: int, sizes?: list<int>}>,
     *     payment_label?: string
     * }  $payload
     */
    public function create(User $user, array $payload): Order
    {
        $quote = $this->quotes->quote($payload);

        return DB::transaction(function () use ($user, $payload, $quote) {
            $type = $this->resolveType($quote['lines']);
            $number = $this->nextNumber();
            $now = now();
            $sessionEnds = $this->sessionEndsAt($payload['time']);

            $order = Order::query()->create([
                'user_id' => $user->id,
                'establishment_id' => $payload['establishment_id'],
                'type' => $type,
                'status' => $type === OrderType::Tickets ? OrderStatus::Confirmed : OrderStatus::Accepted,
                'number' => $number,
                'code' => 'HRG-'.$number,
                'session_date' => $payload['date'],
                'session_time' => $payload['time'].':00',
                'session_ends_at' => $sessionEnds,
                'subtotal' => $quote['subtotal'],
                'discount_total' => $quote['discount_total'],
                'total' => $quote['total'],
                'items_count' => $quote['items_count'],
                'summary_title' => $this->summaryTitle($quote['lines']),
                'summary_short' => $this->summaryShort($quote['lines']),
                'payment_label' => $payload['payment_label'] ?? 'Kaspi Gold · 4417',
                'points_to_earn' => (int) floor($quote['total'] / 20),
                'prep_minutes' => $type === OrderType::Venue ? 5 : null,
                'paid_at' => $now,
                'accepted_at' => $now,
                'preparing_at' => null,
                'ready_at' => null,
                'completed_at' => null,
            ]);

            foreach (array_values($quote['lines']) as $index => $line) {
                $name = $line['name'] ?? $line['code'] ?? '';

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'kind' => $line['kind'],
                    'name' => $name,
                    'subtitle' => $this->itemSubtitle($line),
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'amount' => $line['amount'],
                    'sizes' => $line['sizes'] ?: null,
                    'icon_key' => $this->iconKey($line['kind'], $name),
                    'is_discount' => (bool) $line['is_discount'],
                    'sort_order' => $index + 1,
                ]);
            }

            return $order->load(['items', 'establishment']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function resolveType(array $lines): OrderType
    {
        foreach ($lines as $line) {
            if (in_array($line['kind'], [
                ProductKind::Ticket->value,
                ...ProductKind::rentalValues(),
            ], true)) {
                return OrderType::Tickets;
            }
        }

        return OrderType::Venue;
    }

    private function nextNumber(): int
    {
        do {
            $number = random_int(1000, 9999);
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }

    private function sessionEndsAt(string $time): string
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        $ends = (($hours * 60) + $minutes + 60) % (24 * 60);

        return sprintf('%02d:%02d:00', intdiv($ends, 60), $ends % 60);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function summaryTitle(array $lines): string
    {
        $names = collect($lines)
            ->reject(fn (array $line) => $line['is_discount'])
            ->map(fn (array $line) => $line['name'] ?? $line['code'] ?? null)
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return 'Заказ';
        }

        if ($names->count() === 1) {
            return $names->first();
        }

        if ($names->count() === 2) {
            return $names[0].' и '.$names[1];
        }

        return $names->take($names->count() - 1)->implode(', ').' и '.$names->last();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function summaryShort(array $lines): string
    {
        $productLines = collect($lines)->reject(fn (array $line) => $line['is_discount']);

        $tickets = $productLines->where('kind', ProductKind::Ticket->value)->sum('quantity');
        $skates = $productLines->where('kind', ProductKind::RentalSkates->value)->sum('quantity');
        $helmets = $productLines->where('kind', ProductKind::RentalHelmet->value)->sum('quantity');
        $supports = $productLines->where('kind', ProductKind::RentalSupport->value)->sum('quantity');
        $food = $productLines->where('kind', ProductKind::Food->value)->sum('quantity');

        if ($tickets > 0 || $skates > 0 || $helmets > 0 || $supports > 0) {
            $parts = [];
            if ($tickets > 0) {
                $parts[] = $tickets.' '.$this->plural($tickets, 'билет', 'билета', 'билетов');
            }
            if ($skates > 0) {
                $parts[] = 'коньки';
            }
            if ($helmets > 0) {
                $parts[] = 'шлем';
            }
            if ($supports > 0) {
                $parts[] = 'помогайка';
            }

            return implode(', ', $parts);
        }

        return $food.' '.$this->plural($food, 'позиция', 'позиции', 'позиций');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function itemSubtitle(array $line): ?string
    {
        if ($line['is_discount']) {
            return null;
        }

        if ($line['kind'] === ProductKind::RentalSkates->value) {
            $sizes = collect($line['sizes'] ?? [])->map(fn ($size) => (string) $size)->implode(' и ');

            return $sizes !== '' ? "Размеры {$sizes} · пара" : 'пара';
        }

        if ($line['kind'] === ProductKind::Ticket->value) {
            return 'Сеанс 60 минут';
        }

        if ($line['kind'] === ProductKind::RentalHelmet->value) {
            return 'Универсальный размер';
        }

        if ($line['kind'] === ProductKind::RentalSupport->value) {
            return '1 сеанс';
        }

        return null;
    }

    private function iconKey(string $kind, string $name): ?string
    {
        return match ($kind) {
            ProductKind::Ticket->value => 'ticket',
            ProductKind::RentalSkates->value => 'skates',
            ProductKind::RentalHelmet->value => 'helmet',
            ProductKind::RentalSupport->value => 'support',
            ProductKind::Food->value => Str::slug($name),
            default => null,
        };
    }

    private function plural(int $count, string $one, string $few, string $many): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $one;
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $few;
        }

        return $many;
    }
}
