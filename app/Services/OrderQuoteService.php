<?php

namespace App\Services;

use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\Product;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderQuoteService
{
    private const WEEKDAYS = [
        0 => 'вс',
        1 => 'пн',
        2 => 'вт',
        3 => 'ср',
        4 => 'чт',
        5 => 'пт',
        6 => 'сб',
    ];

    private const MONTHS = [
        1 => 'января',
        2 => 'февраля',
        3 => 'марта',
        4 => 'апреля',
        5 => 'мая',
        6 => 'июня',
        7 => 'июля',
        8 => 'августа',
        9 => 'сентября',
        10 => 'октября',
        11 => 'ноября',
        12 => 'декабря',
    ];

    /**
     * @param  array{
     *     establishment_id: int,
     *     date: string,
     *     time: string,
     *     items: list<array{product_id: int, quantity?: int, sizes?: list<int>}>
     * }  $payload
     * @return array<string, mixed>
     */
    public function quote(array $payload): array
    {
        $establishment = Establishment::query()
            ->active()
            ->findOrFail($payload['establishment_id']);

        $date = Carbon::parse($payload['date'])->startOfDay();
        $time = $payload['time'];

        $products = Product::query()
            ->with('skateSizes')
            ->active()
            ->where('establishment_id', $establishment->id)
            ->whereIn('id', collect($payload['items'])->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $lines = collect();

        foreach ($payload['items'] as $item) {
            $product = $products->get($item['product_id']);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'items' => ["Товар #{$item['product_id']} недоступен."],
                ]);
            }

            $lines->push($this->buildLine($product, $item));
        }

        $discountLines = $this->discounts($lines, $date);
        $allLines = $lines->concat($discountLines);

        $itemsCount = $lines->sum(fn (array $line) => $line['quantity']);
        $subtotal = $lines->sum(fn (array $line) => $line['amount']);
        $discountTotal = abs($discountLines->sum(fn (array $line) => $line['amount']));
        $total = $subtotal - $discountTotal;

        $sessionLabel = $this->sessionLabel($date, $time);
        $shortSessionLabel = $this->shortSessionLabel($date, $time);
        $receiptGroups = $this->receiptGroups($lines, $discountLines);

        return [
            'establishment' => [
                'id' => $establishment->id,
                'name' => $establishment->name,
                'short_name' => $this->shortName($establishment->name),
                'address' => $establishment->address,
            ],
            'session' => [
                'date' => $date->toDateString(),
                'time' => $time,
                'label' => $sessionLabel,
                'short_label' => $shortSessionLabel,
                'when_and_where' => "{$sessionLabel} · {$establishment->address}",
            ],
            'rental_note' => 'Выдаём на стойке по коду брони',
            'food_note' => 'Заберёте у стойки — попадёт в тот же чек',
            'cancellation_note' => 'Отменить бронь бесплатно можно до 14:00 в день сеанса',
            'lines' => $allLines->values()->all(),
            'receipt_groups' => $receiptGroups,
            'items_count' => $itemsCount,
            'subtotal' => $subtotal,
            'subtotal_label' => Money::format($subtotal),
            'discount_total' => $discountTotal,
            'discount_total_label' => Money::format(-$discountTotal),
            'total' => $total,
            'total_label' => Money::format($total),
            'pay_label' => Money::format($total),
            'summary_label' => "{$itemsCount} ".$this->positionsWord($itemsCount)." · {$shortSessionLabel}",
            'footer' => [
                'total' => $total,
                'total_label' => Money::format($total),
                'subtitle' => "{$itemsCount} ".$this->positionsWord($itemsCount)." · {$shortSessionLabel}",
            ],
        ];
    }

    /**
     * @param  array{product_id: int, quantity?: int, sizes?: list<int>}  $item
     * @return array<string, mixed>
     */
    private function buildLine(Product $product, array $item): array
    {
        if ($product->kind === ProductKind::RentalSkates) {
            $sizes = collect($item['sizes'] ?? [])->map(fn ($size) => (int) $size)->values();

            if ($sizes->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => ['Для коньков выберите хотя бы один размер.'],
                ]);
            }

            $available = $product->skateSizes
                ->where('is_available', true)
                ->pluck('size')
                ->all();

            foreach ($sizes as $size) {
                if (! in_array($size, $available, true)) {
                    throw ValidationException::withMessages([
                        'items' => ["Размер {$size} недоступен."],
                    ]);
                }
            }

            $quantity = $sizes->count();
            $amount = $quantity * $product->price;
            $sizeLabels = $sizes->map(fn (int $size) => "р. {$size}")->implode(', ');

            return [
                'product_id' => $product->id,
                'kind' => $product->kind->value,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'amount' => $amount,
                'amount_label' => Money::format($amount),
                'sizes' => $sizes->all(),
                'title' => "{$product->name} × {$quantity} · {$sizeLabels}",
                'is_discount' => false,
            ];
        }

        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $amount = $quantity * $product->price;

        return [
            'product_id' => $product->id,
            'kind' => $product->kind->value,
            'name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'amount' => $amount,
            'amount_label' => Money::format($amount),
            'sizes' => [],
            'title' => "{$product->name} × {$quantity}",
            'is_discount' => false,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return Collection<int, array<string, mixed>>
     */
    private function discounts(Collection $lines, Carbon $date): Collection
    {
        $discounts = collect();

        $skates = $lines->firstWhere('kind', ProductKind::RentalSkates->value);

        if ($skates && $date->isWeekday()) {
            $amount = -1 * (int) $skates['unit_price'];
            $discounts->push([
                'product_id' => null,
                'kind' => 'discount',
                'name' => 'Скидка на коньки, будни',
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
                'amount_label' => Money::format($amount),
                'sizes' => [],
                'title' => 'Скидка на коньки, будни',
                'is_discount' => true,
            ]);
        } elseif ($skates && $skates['quantity'] >= 2) {
            // weekend promo from first mock: one pair discounted when multiple pairs
            $amount = -1 * (int) $skates['unit_price'];
            $discounts->push([
                'product_id' => null,
                'kind' => 'discount',
                'name' => 'Скидка на коньки',
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
                'amount_label' => Money::format($amount),
                'sizes' => [],
                'title' => 'Скидка на коньки',
                'is_discount' => true,
            ]);
        }

        return $discounts;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  Collection<int, array<string, mixed>>  $discountLines
     * @return list<array{key: string, title: string, amount: int, amount_label: string, is_discount: bool}>
     */
    private function receiptGroups(Collection $lines, Collection $discountLines): array
    {
        $ticketsAndRentals = $lines
            ->filter(fn (array $line) => in_array($line['kind'], [
                ProductKind::Ticket->value,
                ProductKind::RentalSkates->value,
                ProductKind::RentalHelmet->value,
            ], true))
            ->sum(fn (array $line) => $line['amount']);

        $buffet = $lines
            ->filter(fn (array $line) => $line['kind'] === ProductKind::Food->value)
            ->sum(fn (array $line) => $line['amount']);

        $discountTotal = abs($discountLines->sum(fn (array $line) => $line['amount']));

        $groups = [];

        if ($ticketsAndRentals > 0) {
            $groups[] = [
                'key' => 'tickets_and_rentals',
                'title' => 'Билеты и аренда',
                'amount' => $ticketsAndRentals,
                'amount_label' => Money::format($ticketsAndRentals),
                'is_discount' => false,
            ];
        }

        if ($buffet > 0) {
            $groups[] = [
                'key' => 'buffet',
                'title' => 'Буфет',
                'amount' => $buffet,
                'amount_label' => Money::format($buffet),
                'is_discount' => false,
            ];
        }

        if ($discountTotal > 0) {
            $groups[] = [
                'key' => 'discounts',
                'title' => 'Скидка и баллы',
                'amount' => -$discountTotal,
                'amount_label' => Money::format(-$discountTotal),
                'is_discount' => true,
            ];
        }

        return $groups;
    }

    private function sessionLabel(Carbon $date, string $time): string
    {
        $weekday = self::WEEKDAYS[$date->dayOfWeek];
        $month = self::MONTHS[$date->month];

        return "{$weekday}, {$date->day} {$month} · {$time}";
    }

    private function shortSessionLabel(Carbon $date, string $time): string
    {
        $weekday = self::WEEKDAYS[$date->dayOfWeek];

        return "{$weekday}, {$date->day} · {$time}";
    }

    private function shortName(string $name): string
    {
        return str_contains($name, 'Ледовая арена') ? 'Ледовая арена' : $name;
    }

    private function positionsWord(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return 'позиция';
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 'позиции';
        }

        return 'позиций';
    }
}
