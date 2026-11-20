<?php

namespace App\Services\TipTopPay;

use App\Models\Order;
use App\Models\User;

class TipTopPayReceiptBuilder
{
    /**
     * TipTop Kassir / PaymentData.CustomerReceipt for online fiscal receipts.
     *
     * @return array<string, mixed>|null
     */
    public function customerReceipt(Order $order): ?array
    {
        $order->loadMissing(['items', 'user']);

        $items = [];

        foreach ($order->items as $item) {
            if ($item->is_discount) {
                continue;
            }

            $quantity = max(1, (int) $item->quantity);
            $price = (float) $item->unit_price;
            $amount = (float) $item->amount;

            if ($amount <= 0 && $price <= 0) {
                continue;
            }

            if ($amount <= 0) {
                $amount = $price * $quantity;
            }

            $items[] = [
                'Label' => mb_substr((string) $item->name, 0, 128),
                'Price' => round($price, 2),
                'Quantity' => $quantity,
                'Amount' => round($amount, 2),
                'Vat' => $this->vat(),
                'MeasurementUnit' => 'шт',
            ];
        }

        if ($items === []) {
            return null;
        }

        $receipt = [
            'Items' => $items,
            'Amounts' => [
                'Electronic' => round((float) $order->total, 2),
            ],
        ];

        $user = $order->user;

        if ($user instanceof User) {
            if (filled($user->email)) {
                $receipt['Email'] = (string) $user->email;
            }

            if (filled($user->phone)) {
                $phone = preg_replace('/\D+/', '', (string) $user->phone);

                if (filled($phone)) {
                    $receipt['Phone'] = $phone;
                }
            }
        }

        return $receipt;
    }

    /**
     * @return array{PaymentData: array{CustomerReceipt: array<string, mixed>}}|null
     */
    public function jsonData(Order $order): ?array
    {
        $receipt = $this->customerReceipt($order);

        if ($receipt === null) {
            return null;
        }

        return [
            'PaymentData' => [
                'CustomerReceipt' => $receipt,
            ],
        ];
    }

    private function vat(): ?int
    {
        $vat = config('services.tiptop_pay.receipt_vat');

        if ($vat === null || $vat === '') {
            return null;
        }

        return (int) $vat;
    }
}
