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
    public function customerReceipt(Order $order, ?float $electronicAmount = null): ?array
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

        $target = round($electronicAmount ?? (float) $order->total, 2);

        if ($target <= 0) {
            return null;
        }

        $items = $this->alignItemAmounts($items, $target);

        $receipt = [
            'Items' => $items,
            'Amounts' => [
                'Electronic' => $target,
            ],
        ];

        $taxationSystem = $this->taxationSystem();

        if ($taxationSystem !== null) {
            $receipt['TaxationSystem'] = $taxationSystem;
        }

        $user = $order->user;

        if ($user instanceof User && filled($user->phone)) {
            $phone = preg_replace('/\D+/', '', (string) $user->phone);

            if (filled($phone)) {
                $receipt['Phone'] = $phone;
            }
        }

        return $receipt;
    }

    /**
     * @return array{PaymentData: array{CustomerReceipt: array<string, mixed>}}|null
     */
    public function jsonData(Order $order): ?array
    {
        return $this->wrap($this->customerReceipt($order));
    }

    /**
     * Refund receipt tied to a TipTop payments/refund call (IncomeReturn).
     *
     * @return array{PaymentData: array{CustomerReceipt: array<string, mixed>}}|null
     */
    public function refundJsonData(Order $order, int $refundAmount): ?array
    {
        if ($refundAmount < 1) {
            return null;
        }

        return $this->wrap($this->customerReceipt($order, (float) $refundAmount));
    }

    /**
     * @param  array<string, mixed>|null  $receipt
     * @return array{PaymentData: array{CustomerReceipt: array<string, mixed>}}|null
     */
    private function wrap(?array $receipt): ?array
    {
        if ($receipt === null) {
            return null;
        }

        return [
            'PaymentData' => [
                'CustomerReceipt' => $receipt,
            ],
        ];
    }

    /**
     * Kassir requires sum(Items.Amount) === Amounts.Electronic (order total after discounts).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function alignItemAmounts(array $items, float $target): array
    {
        $sum = round(array_sum(array_map(
            static fn (array $item): float => (float) $item['Amount'],
            $items,
        )), 2);

        if (abs($sum - $target) < 0.005) {
            return $items;
        }

        if ($sum <= 0) {
            return $items;
        }

        $ratio = $target / $sum;
        $allocated = 0.0;
        $lastIndex = count($items) - 1;

        foreach ($items as $index => &$item) {
            if ($index === $lastIndex) {
                $amount = round($target - $allocated, 2);
            } else {
                $amount = round((float) $item['Amount'] * $ratio, 2);
                $allocated += $amount;
            }

            $quantity = max(1, (int) $item['Quantity']);
            $item['Amount'] = $amount;
            $item['Price'] = round($amount / $quantity, 2);
        }
        unset($item);

        return $items;
    }

    private function vat(): ?int
    {
        $vat = config('services.tiptop_pay.receipt_vat');

        if ($vat === null || $vat === '') {
            return null;
        }

        return (int) $vat;
    }

    private function taxationSystem(): ?int
    {
        $value = config('services.tiptop_pay.receipt_taxation_system');

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
