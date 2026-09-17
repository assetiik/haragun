<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\ProductKind;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Establishment;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreate(User $user, Establishment $establishment): Cart
    {
        $this->ensureCoffee($establishment);

        return Cart::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'establishment_id' => $establishment->id,
            ],
            [
                'payment_method' => PaymentMethod::Kaspi,
            ],
        );
    }

    public function load(Cart $cart): Cart
    {
        return $cart->load([
            'user',
            'establishment',
            'items' => fn ($query) => $query->orderBy('id'),
            'items.product',
            'items.size',
        ]);
    }

    /**
     * @param  array{product_id: int, size_id?: int|null, modifier_ids?: list<int>, quantity?: int}  $payload
     */
    public function addItem(Cart $cart, array $payload): Cart
    {
        $priced = $this->priceLine(
            $cart->establishment_id,
            (int) $payload['product_id'],
            isset($payload['size_id']) ? (int) $payload['size_id'] : null,
            $payload['modifier_ids'] ?? [],
        );

        $quantity = max(1, (int) ($payload['quantity'] ?? 1));
        $signature = CartItem::buildSignature(
            $priced['product']->id,
            $priced['size']?->id,
            $priced['modifier_ids'],
        );

        $existing = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('signature', $signature)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'quantity' => min(20, $existing->quantity + $quantity),
                'unit_price' => $priced['unit_price'],
            ]);
        } else {
            CartItem::query()->create([
                'cart_id' => $cart->id,
                'product_id' => $priced['product']->id,
                'product_size_id' => $priced['size']?->id,
                'modifier_ids' => $priced['modifier_ids'],
                'signature' => $signature,
                'quantity' => min(20, $quantity),
                'unit_price' => $priced['unit_price'],
            ]);
        }

        return $this->load($cart->refresh());
    }

    public function updateQuantity(Cart $cart, CartItem $item, int $quantity): Cart
    {
        $this->ensureItemBelongs($cart, $item);

        if ($quantity <= 0) {
            $item->delete();
        } else {
            $item->update(['quantity' => min(20, $quantity)]);
        }

        return $this->load($cart->refresh());
    }

    public function removeItem(Cart $cart, CartItem $item): Cart
    {
        $this->ensureItemBelongs($cart, $item);
        $item->delete();

        return $this->load($cart->refresh());
    }

    public function clear(Cart $cart): Cart
    {
        $cart->items()->delete();

        return $this->load($cart->refresh());
    }

    public function setPaymentMethod(Cart $cart, PaymentMethod $method): Cart
    {
        $cart->update(['payment_method' => $method]);

        return $this->load($cart->refresh());
    }

    /**
     * @return array{order: Order, cart: Cart}
     */
    public function checkout(Cart $cart, ?string $paymentLabel = null): array
    {
        $cart = $this->load($cart);

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Корзина пуста.'],
            ]);
        }

        $order = DB::transaction(function () use ($cart, $paymentLabel) {
            $number = $this->nextNumber();
            $now = now();
            $items = $cart->items;
            $subtotal = $cart->total();
            $lines = $items->map(fn (CartItem $item) => [
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'subtitle' => $item->subtitle(),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $item->amount(),
                'image_url' => $item->product->image_url,
            ])->all();

            $order = Order::query()->create([
                'user_id' => $cart->user_id,
                'establishment_id' => $cart->establishment_id,
                'type' => OrderType::Venue,
                'status' => OrderStatus::Accepted,
                'number' => $number,
                'guest_number' => $cart->user->guestNumber(),
                'code' => 'HRG-'.$number,
                'session_date' => $now->toDateString(),
                'session_time' => $now->format('H:i:s'),
                'session_ends_at' => null,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'total' => $subtotal,
                'items_count' => $cart->itemsCount(),
                'summary_title' => $this->summaryTitle($lines),
                'summary_short' => $this->summaryShort($cart->itemsCount()),
                'payment_label' => $paymentLabel ?: $cart->payment_method->orderLabel(),
                'points_to_earn' => (int) floor($subtotal / 20),
                'prep_minutes' => 15,
                'paid_at' => $now,
                'accepted_at' => $now,
                'preparing_at' => $now,
                'ready_at' => null,
                'completed_at' => null,
            ]);

            foreach (array_values($lines) as $index => $line) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $line['product_id'],
                    'kind' => ProductKind::Food->value,
                    'name' => $line['name'],
                    'subtitle' => $line['subtitle'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'amount' => $line['amount'],
                    'sizes' => null,
                    'icon_key' => Str::slug($line['name']),
                    'image_url' => $line['image_url'],
                    'is_discount' => false,
                    'sort_order' => $index + 1,
                ]);
            }

            $cart->items()->delete();

            return $order->load(['items.product', 'establishment', 'feedback', 'user']);
        });

        return [
            'order' => $order,
            'cart' => $this->load($cart->refresh()),
        ];
    }

    /**
     * @return array{items_count: int, total: int, total_label: string, label: string}
     */
    public function summary(?Cart $cart): array
    {
        $count = $cart?->itemsCount() ?? 0;
        $total = $cart?->total() ?? 0;

        return [
            'items_count' => $count,
            'total' => $total,
            'total_label' => Money::format($total),
            'label' => "Корзина · {$count} шт",
        ];
    }

    public function activeOrderBanner(User $user, Establishment $establishment): ?array
    {
        $order = Order::query()
            ->where('user_id', $user->id)
            ->where('establishment_id', $establishment->id)
            ->ofType(OrderType::Venue)
            ->whereIn('status', [
                OrderStatus::Accepted->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
            ])
            ->latest('id')
            ->first();

        if ($order === null || ! $order->isActive()) {
            return null;
        }

        $statusLabel = match ($order->status) {
            OrderStatus::Accepted => 'Принят',
            OrderStatus::Preparing => 'Готовится',
            OrderStatus::Ready => 'Готов',
            default => $order->status->value,
        };

        return [
            'id' => $order->id,
            'number' => $order->number,
            'number_label' => $order->numberLabel(),
            'code' => $order->code,
            'status' => $order->status->value,
            'status_label' => $statusLabel,
            'title' => $order->statusBanner()['title'],
            'subtitle' => $order->statusBanner()['subtitle'],
        ];
    }

    /**
     * @param  list<int>  $modifierIds
     * @return array{
     *     product: Product,
     *     size: ?ProductSize,
     *     modifiers: list<Modifier>,
     *     modifier_ids: list<int>,
     *     unit_price: int,
     *     subtitle: string
     * }
     */
    public function priceLine(int $establishmentId, int $productId, ?int $sizeId, array $modifierIds): array
    {
        $product = Product::query()
            ->with(['sizes', 'modifiers' => fn ($query) => $query->active()])
            ->where('establishment_id', $establishmentId)
            ->coffeeMenu()
            ->active()
            ->findOrFail($productId);

        $size = null;
        if ($sizeId !== null) {
            $size = $product->sizes->firstWhere('id', $sizeId);
            if ($size === null) {
                throw ValidationException::withMessages([
                    'size_id' => ['Выбранный размер недоступен.'],
                ]);
            }
        } else {
            $size = $product->defaultSize();
        }

        $unitPrice = $size?->price ?? $product->price;
        $modifiers = [];
        $normalizedIds = collect($modifierIds)->map(fn ($id) => (int) $id)->unique()->sort()->values();

        foreach ($normalizedIds as $modifierId) {
            $modifier = Modifier::query()
                ->whereKey($modifierId)
                ->active()
                ->first();

            if ($modifier === null) {
                throw ValidationException::withMessages([
                    'modifier_ids' => ["Добавка #{$modifierId} недоступна."],
                ]);
            }

            if (! $product->modifiers->contains('id', $modifier->id)) {
                throw ValidationException::withMessages([
                    'modifier_ids' => ["Добавка {$modifier->name} не подходит к этому напитку."],
                ]);
            }

            $modifiers[] = $modifier;
            $unitPrice += $modifier->price;
        }

        return [
            'product' => $product,
            'size' => $size,
            'modifiers' => $modifiers,
            'modifier_ids' => $normalizedIds->all(),
            'unit_price' => $unitPrice,
            'subtitle' => $this->buildSubtitle($size, $modifiers, $product),
        ];
    }

    /**
     * @param  list<Modifier>  $modifiers
     */
    public function buildSubtitle(?ProductSize $size, array $modifiers, Product $product): string
    {
        $parts = [];

        if ($size !== null) {
            $parts[] = $size->label;
        } elseif (filled($product->meta)) {
            $parts[] = $product->meta;
        }

        foreach ($modifiers as $modifier) {
            $parts[] = $modifier->name;
        }

        if ($parts === [] && filled($product->description)) {
            $parts[] = $product->description;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  list<array{name: string}>  $lines
     */
    private function summaryTitle(array $lines): string
    {
        $names = collect($lines)->pluck('name')->unique()->values();

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

    private function summaryShort(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        $word = match (true) {
            $mod10 === 1 && $mod100 !== 11 => 'напиток',
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 'напитка',
            default => 'напитков',
        };

        return "{$count} {$word}";
    }

    private function nextNumber(): int
    {
        do {
            $number = random_int(100, 999);
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }

    private function ensureCoffee(Establishment $establishment): void
    {
        abort_unless($establishment->offersCoffeeMenu(), 404);
    }

    private function ensureItemBelongs(Cart $cart, CartItem $item): void
    {
        abort_unless($item->cart_id === $cart->id, 404);
    }
}
