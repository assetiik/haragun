<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\UserNotification;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->recordStatus($order);
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $this->recordStatus($order);
    }

    private function recordStatus(Order $order): void
    {
        $user = $order->user;

        if ($user === null || ! $user->notifications_enabled) {
            return;
        }

        UserNotification::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'establishment_id' => $order->establishment_id,
            'type' => 'order_status',
            'status' => $order->status,
            'order_number' => $order->number,
            'order_type' => $order->type,
        ]);
    }
}
