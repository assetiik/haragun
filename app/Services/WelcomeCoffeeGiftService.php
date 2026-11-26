<?php

namespace App\Services;

use App\Enums\EstablishmentType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use App\Models\WelcomeCoffeeGift;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WelcomeCoffeeGiftService
{
    public const TITLE = '1 эспрессо в подарок';

    public const SUBTITLE = 'Только в кофейнях Haragun Express';

    /**
     * @return array{available: bool, title: string, subtitle: string}|null
     */
    public function payloadFor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $gift = $this->availableGiftFor($user);

        if ($gift === null) {
            return [
                'available' => false,
                'title' => self::TITLE,
                'subtitle' => self::SUBTITLE,
            ];
        }

        return [
            'available' => true,
            'title' => self::TITLE,
            'subtitle' => self::SUBTITLE,
        ];
    }

    public function grantOnFirstRegistration(User $user): void
    {
        if (WelcomeCoffeeGift::query()->where('phone', $user->phone)->exists()) {
            return;
        }

        WelcomeCoffeeGift::query()->create([
            'phone' => $user->phone,
            'user_id' => $user->id,
            'status' => WelcomeCoffeeGift::STATUS_AVAILABLE,
            'granted_at' => now(),
        ]);
    }

    public function rebindToUser(User $user): void
    {
        WelcomeCoffeeGift::query()
            ->where('phone', $user->phone)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);
    }

    public function forfeitForDeletedUser(User $user): void
    {
        WelcomeCoffeeGift::query()
            ->where('phone', $user->phone)
            ->whereIn('status', [
                WelcomeCoffeeGift::STATUS_AVAILABLE,
                WelcomeCoffeeGift::STATUS_RESERVED,
            ])
            ->update([
                'status' => WelcomeCoffeeGift::STATUS_REDEEMED,
                'user_id' => null,
                'reserved_order_id' => null,
                'redeemed_at' => now(),
            ]);
    }

    public function availableGiftFor(User $user): ?WelcomeCoffeeGift
    {
        return WelcomeCoffeeGift::query()
            ->where('phone', $user->phone)
            ->where('status', WelcomeCoffeeGift::STATUS_AVAILABLE)
            ->first();
    }

    public function cartHasEligibleEspresso(Cart $cart): bool
    {
        $cart->loadMissing(['items.product', 'establishment']);

        if ($cart->establishment?->type !== EstablishmentType::Coffee) {
            return false;
        }

        return $cart->items->contains(
            fn (CartItem $item): bool => (bool) $item->product?->is_welcome_espresso
        );
    }

    public function giftUnitDiscount(Cart $cart): int
    {
        if (! $cart->apply_welcome_gift || ! $this->cartHasEligibleEspresso($cart)) {
            return 0;
        }

        if ($this->availableGiftFor($cart->user) === null) {
            return 0;
        }

        $item = $cart->items->first(
            fn (CartItem $cartItem): bool => (bool) $cartItem->product?->is_welcome_espresso
        );

        return $item !== null ? (int) $item->unit_price : 0;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function applyGiftToCheckoutLines(Cart $cart, array $lines): array
    {
        $cart->loadMissing(['user', 'establishment', 'items.product']);

        if (! $cart->apply_welcome_gift) {
            return $lines;
        }

        if ($cart->establishment?->type !== EstablishmentType::Coffee) {
            throw ValidationException::withMessages([
                'apply_welcome_gift' => ['Подарок доступен только в кофейнях.'],
            ]);
        }

        if ($this->availableGiftFor($cart->user) === null) {
            throw ValidationException::withMessages([
                'apply_welcome_gift' => ['Подарочный эспрессо недоступен.'],
            ]);
        }

        $giftIndex = null;

        foreach ($lines as $index => $line) {
            $product = $cart->items->firstWhere('product_id', $line['product_id'])?->product;

            if ($product?->is_welcome_espresso) {
                $giftIndex = $index;
                break;
            }
        }

        if ($giftIndex === null) {
            throw ValidationException::withMessages([
                'apply_welcome_gift' => ['Добавьте эспрессо в заказ, чтобы применить подарок.'],
            ]);
        }

        $line = $lines[$giftIndex];
        $quantity = (int) $line['quantity'];
        $unitPrice = (int) $line['unit_price'];
        $result = [];

        foreach ($lines as $index => $current) {
            if ($index !== $giftIndex) {
                $result[] = $current;

                continue;
            }

            if ($quantity > 1) {
                $result[] = [
                    ...$current,
                    'quantity' => $quantity - 1,
                    'amount' => $unitPrice * ($quantity - 1),
                ];
            }

            $result[] = [
                ...$current,
                'quantity' => 1,
                'unit_price' => 0,
                'amount' => 0,
                'subtitle' => trim(($current['subtitle'] ?? '').' · в подарок', ' ·'),
                'counts_toward_coffee_card' => false,
                'is_welcome_gift' => true,
            ];
        }

        return $result;
    }

    public function reserveForOrder(User $user, Order $order): void
    {
        DB::transaction(function () use ($user, $order): void {
            $gift = WelcomeCoffeeGift::query()
                ->where('phone', $user->phone)
                ->where('status', WelcomeCoffeeGift::STATUS_AVAILABLE)
                ->lockForUpdate()
                ->first();

            if ($gift === null) {
                throw ValidationException::withMessages([
                    'apply_welcome_gift' => ['Подарочный эспрессо недоступен.'],
                ]);
            }

            $gift->forceFill([
                'user_id' => $user->id,
                'status' => WelcomeCoffeeGift::STATUS_RESERVED,
                'reserved_order_id' => $order->id,
            ])->save();
        });
    }

    public function redeemForPaidOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $gift = WelcomeCoffeeGift::query()
                ->where('reserved_order_id', $order->id)
                ->where('status', WelcomeCoffeeGift::STATUS_RESERVED)
                ->lockForUpdate()
                ->first();

            if ($gift === null) {
                return;
            }

            $gift->forceFill([
                'status' => WelcomeCoffeeGift::STATUS_REDEEMED,
                'redeemed_order_id' => $order->id,
                'redeemed_at' => now(),
            ])->save();
        });
    }

    /**
     * @param  Collection<int, int>|list<int>  $orderIds
     */
    public function releaseForOrders(Collection|array $orderIds): void
    {
        $ids = Collection::wrap($orderIds)->filter()->values();

        if ($ids->isEmpty()) {
            return;
        }

        WelcomeCoffeeGift::query()
            ->whereIn('reserved_order_id', $ids->all())
            ->where('status', WelcomeCoffeeGift::STATUS_RESERVED)
            ->update([
                'status' => WelcomeCoffeeGift::STATUS_AVAILABLE,
                'reserved_order_id' => null,
            ]);
    }

    public function orderHasWelcomeGift(Order $order): bool
    {
        $order->loadMissing('items');

        return $order->items->contains(
            fn ($item): bool => (bool) $item->is_welcome_gift
        );
    }
}
