<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\DssSoft\DssSoftCatalog;
use App\Services\DssSoft\DssSoftClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OrderService
{
    public function __construct(
        private OrderQuoteService $quotes,
        private DssSoftCatalog $dss,
        private DssSoftClient $dssClient,
    ) {}

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
        $type = $this->resolveType($quote['lines']);
        $establishment = Establishment::query()->findOrFail($payload['establishment_id']);
        $ticketLines = collect($quote['lines'])
            ->filter(fn (array $line): bool => $line['kind'] === ProductKind::Ticket->value && ! $line['is_discount']);

        $slot = null;
        $dssHold = null;
        $order = null;
        $confirmed = false;

        try {
            if ($type === OrderType::Tickets && $this->dss->usesDss($establishment) && $ticketLines->isNotEmpty()) {
                $slot = $this->dss->resolveSlot($payload['date'], $payload['time']);
                $dssHold = $this->dssClient->createOrder($this->dssOrderPayload($user, $ticketLines->all(), $slot));
            }

            $order = DB::transaction(function () use ($user, $payload, $quote, $type, $slot, $dssHold) {
                $number = $this->nextNumber();
                $now = now();
                $sessionEnds = $slot['ends_at'] ?? $this->sessionEndsAt($payload['time']);

                $order = Order::query()->create([
                    'user_id' => $user->id,
                    'establishment_id' => $payload['establishment_id'],
                    'type' => $type,
                    'status' => $type === OrderType::Tickets ? OrderStatus::Confirmed : OrderStatus::Accepted,
                    'number' => $number,
                    'code' => 'HRG-'.$number,
                    'dss_order_id' => $dssHold['id'] ?? null,
                    'dss_day_id' => $slot['dss_day_id'] ?? null,
                    'dss_slot_id' => $slot['dss_slot_id'] ?? null,
                    'session_date' => $payload['date'],
                    'session_time' => $payload['time'].':00',
                    'session_ends_at' => strlen($sessionEnds) === 5 ? $sessionEnds.':00' : $sessionEnds,
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

                return $order;
            });

            if (is_array($dssHold) && isset($dssHold['id'])) {
                $dssOrder = $this->dssClient->confirmOrder((int) $dssHold['id']);
                $confirmed = true;
                $this->attachBarcodes($order, $dssOrder, $ticketLines->all());
            }

            return $order->load(['items', 'establishment']);
        } catch (Throwable $exception) {
            if (! $confirmed && is_array($dssHold) && isset($dssHold['id'])) {
                try {
                    $this->dssClient->cancelOrder((int) $dssHold['id']);
                } catch (Throwable $cancelException) {
                    Log::warning('Не удалось отменить бронь DSS-Soft', [
                        'dss_order_id' => $dssHold['id'],
                        'error' => $cancelException->getMessage(),
                    ]);
                }

                $order?->delete();
            }

            throw $exception;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $ticketLines
     * @param  array{dss_day_id: int, dss_slot_id: int}  $slot
     * @return array<string, mixed>
     */
    private function dssOrderPayload(User $user, array $ticketLines, array $slot): array
    {
        $cart = [];

        foreach ($ticketLines as $line) {
            $priceGroupId = (int) ($line['dss_pricegroup_id'] ?? 0);

            for ($index = 0; $index < (int) $line['quantity']; $index++) {
                $cart[] = ['pricegroup_id' => $priceGroupId];
            }
        }

        return [
            'cart' => $cart,
            'name' => filled($user->name) ? $user->name : 'Гость '.$user->guestNumber(),
            'phone' => $this->dssPhone($user),
            'email' => (string) config('haragun.contacts.email'),
            'dayId' => $slot['dss_day_id'],
            'slotId' => $slot['dss_slot_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $dssOrder
     * @param  list<array<string, mixed>>  $ticketLines
     */
    private function attachBarcodes(Order $order, array $dssOrder, array $ticketLines): void
    {
        $byGroup = collect($dssOrder['items'] ?? [])
            ->groupBy(fn (mixed $item): int => (int) data_get($item, 'pricegroup_id', 0))
            ->map(fn ($items) => collect($items)
                ->map(fn (mixed $item): string => (string) data_get($item, 'barcode', ''))
                ->filter()
                ->values());

        $available = $byGroup->flatten()->values();

        foreach ($order->items()->get() as $item) {
            if ($item->kind !== ProductKind::Ticket->value || $item->is_discount) {
                continue;
            }

            $line = collect($ticketLines)->firstWhere('product_id', $item->product_id);
            $groupId = (int) ($line['dss_pricegroup_id'] ?? 0);
            $needed = (int) $item->quantity;
            $codes = [];

            if ($groupId > 0 && $byGroup->has($groupId)) {
                $codes = $byGroup[$groupId]->splice(0, $needed)->all();
            }

            if ($codes === []) {
                $codes = $available->splice(0, $needed)->all();
            }

            $item->update([
                'barcodes' => $codes === [] ? null : array_values($codes),
            ]);
        }
    }

    private function dssPhone(User $user): string
    {
        $digits = preg_replace('/\D+/', '', $user->phone) ?? '';

        if (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7'.substr($digits, 1);
        }

        return str_starts_with($digits, '+') ? $digits : '+'.$digits;
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
