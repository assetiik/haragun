<?php

namespace App\Services;

use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderQuoteService
{
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

        return [
            'establishment' => [
                'id' => $establishment->id,
                'name' => $establishment->name,
                'address' => $establishment->address,
            ],
            'session' => [
                'date' => $date->toDateString(),
                'time' => $time,
            ],
            'lines' => $allLines->values()->all(),
            'receipt_groups' => $this->receiptGroups($lines, $discountLines),
            'items_count' => $itemsCount,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'total' => $total,
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

            return [
                'product_id' => $product->id,
                'kind' => $product->kind->value,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'amount' => $amount,
                'sizes' => $sizes->all(),
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
            'sizes' => [],
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
                'code' => 'weekday_skate_discount',
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
                'sizes' => [],
                'is_discount' => true,
            ]);
        } elseif ($skates && $skates['quantity'] >= 2) {
            // weekend promo from first mock: one pair discounted when multiple pairs
            $amount = -1 * (int) $skates['unit_price'];
            $discounts->push([
                'product_id' => null,
                'kind' => 'discount',
                'code' => 'extra_skate_discount',
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
                'sizes' => [],
                'is_discount' => true,
            ]);
        }

        return $discounts;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  Collection<int, array<string, mixed>>  $discountLines
     * @return list<array{key: string, amount: int, is_discount: bool}>
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
                'amount' => $ticketsAndRentals,
                'is_discount' => false,
            ];
        }

        if ($buffet > 0) {
            $groups[] = [
                'key' => 'buffet',
                'amount' => $buffet,
                'is_discount' => false,
            ];
        }

        if ($discountTotal > 0) {
            $groups[] = [
                'key' => 'discounts',
                'amount' => -$discountTotal,
                'is_discount' => true,
            ];
        }

        return $groups;
    }
}
