<?php

namespace App\Services;

use App\Enums\EstablishmentType;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\CoffeeLoyaltyCard;
use App\Models\CoffeeLoyaltyEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CoffeeLoyaltyService
{
    public const TOTAL_SLOTS = 10;

    public const PAID_STAMPS_FOR_REWARD = 9;

    /**
     * @return array{paid_stamps: int, total_slots: int, stamps_until_reward: int, reward_available: bool}|null
     */
    public function progressFor(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $card = CoffeeLoyaltyCard::query()->where('user_id', $user->id)->first();

        return $this->snapshot($card?->paid_stamps ?? 0);
    }

    /**
     * @return array{paid_stamps: int, total_slots: int, stamps_until_reward: int, reward_available: bool}
     */
    public function snapshot(int $paidStamps): array
    {
        $paid = max(0, min(self::PAID_STAMPS_FOR_REWARD, $paidStamps));

        return [
            'paid_stamps' => $paid,
            'total_slots' => self::TOTAL_SLOTS,
            'stamps_until_reward' => max(0, self::PAID_STAMPS_FOR_REWARD - $paid),
            'reward_available' => $paid >= self::PAID_STAMPS_FOR_REWARD,
        ];
    }

    /**
     * @return array{stamps_earned: int, before: array{paid_stamps: int, total_slots: int, stamps_until_reward: int, reward_available: bool}, after: array{paid_stamps: int, total_slots: int, stamps_until_reward: int, reward_available: bool}}|null
     */
    public function orderPayload(Order $order): ?array
    {
        $event = CoffeeLoyaltyEvent::query()
            ->where('order_id', $order->id)
            ->where('type', CoffeeLoyaltyEvent::TYPE_EARN)
            ->first();

        if ($event === null) {
            return null;
        }

        return [
            'stamps_earned' => (int) $event->stamps,
            'before' => $event->before_snapshot,
            'after' => $event->after_snapshot,
        ];
    }

    public function awardForPaidOrder(Order $order): void
    {
        if (! $this->orderQualifiesForLoyalty($order)) {
            return;
        }

        $requested = $this->stampsRequested($order);

        if ($requested < 1) {
            return;
        }

        DB::transaction(function () use ($order, $requested): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $this->orderQualifiesForLoyalty($lockedOrder)) {
                return;
            }

            if (CoffeeLoyaltyEvent::query()
                ->where('order_id', $lockedOrder->id)
                ->where('type', CoffeeLoyaltyEvent::TYPE_EARN)
                ->exists()) {
                return;
            }

            CoffeeLoyaltyCard::query()->firstOrCreate(
                ['user_id' => $lockedOrder->user_id],
                ['paid_stamps' => 0],
            );

            /** @var CoffeeLoyaltyCard $card */
            $card = CoffeeLoyaltyCard::query()
                ->where('user_id', $lockedOrder->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (int) $card->paid_stamps;
            $applied = min($requested, self::PAID_STAMPS_FOR_REWARD - $before);

            if ($applied < 1) {
                return;
            }

            $after = $before + $applied;
            $beforeSnapshot = $this->snapshot($before);
            $afterSnapshot = $this->snapshot($after);

            $card->forceFill(['paid_stamps' => $after])->save();

            CoffeeLoyaltyEvent::query()->create([
                'user_id' => $lockedOrder->user_id,
                'order_id' => $lockedOrder->id,
                'type' => CoffeeLoyaltyEvent::TYPE_EARN,
                'stamps' => $applied,
                'before_paid_stamps' => $before,
                'after_paid_stamps' => $after,
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => $afterSnapshot,
            ]);
        });
    }

    public function reverseForRefundedOrder(Order $order): void
    {
        if ($order->user_id === null) {
            return;
        }

        DB::transaction(function () use ($order): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $earn = CoffeeLoyaltyEvent::query()
                ->where('order_id', $lockedOrder->id)
                ->where('type', CoffeeLoyaltyEvent::TYPE_EARN)
                ->lockForUpdate()
                ->first();

            if ($earn === null) {
                return;
            }

            if (CoffeeLoyaltyEvent::query()
                ->where('order_id', $lockedOrder->id)
                ->where('type', CoffeeLoyaltyEvent::TYPE_REVERSE)
                ->exists()) {
                return;
            }

            CoffeeLoyaltyCard::query()->firstOrCreate(
                ['user_id' => $lockedOrder->user_id],
                ['paid_stamps' => 0],
            );

            /** @var CoffeeLoyaltyCard $card */
            $card = CoffeeLoyaltyCard::query()
                ->where('user_id', $lockedOrder->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = (int) $card->paid_stamps;
            $applied = min((int) $earn->stamps, $before);
            $after = max(0, $before - $applied);
            $beforeSnapshot = $this->snapshot($before);
            $afterSnapshot = $this->snapshot($after);

            $card->forceFill(['paid_stamps' => $after])->save();

            CoffeeLoyaltyEvent::query()->create([
                'user_id' => $lockedOrder->user_id,
                'order_id' => $lockedOrder->id,
                'type' => CoffeeLoyaltyEvent::TYPE_REVERSE,
                'stamps' => $applied,
                'before_paid_stamps' => $before,
                'after_paid_stamps' => $after,
                'before_snapshot' => $beforeSnapshot,
                'after_snapshot' => $afterSnapshot,
            ]);
        });
    }

    private function orderQualifiesForLoyalty(Order $order): bool
    {
        if ($order->type !== OrderType::Venue || $order->user_id === null) {
            return false;
        }

        if ($order->payment_status !== PaymentStatus::Paid || blank($order->paid_at)) {
            return false;
        }

        $order->loadMissing('establishment');

        return $order->establishment?->type === EstablishmentType::Coffee;
    }

    private function stampsRequested(Order $order): int
    {
        $order->loadMissing('items');

        return (int) $order->items
            ->where('is_discount', false)
            ->where('counts_toward_coffee_card', true)
            ->sum('quantity');
    }
}
