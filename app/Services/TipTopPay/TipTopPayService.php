<?php

namespace App\Services\TipTopPay;

use App\Enums\EstablishmentType;
use App\Enums\PaymentStatus;
use App\Exceptions\TipTopPayException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Services\CoffeeLoyaltyService;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class TipTopPayService
{
    public function __construct(
        private TipTopPayClient $client,
        private TipTopPayReceiptBuilder $receipts,
        private CoffeeLoyaltyService $coffeeLoyalty,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('services.tiptop_pay.enabled') && $this->client->isConfigured();
    }

    public function isEnabledForType(?EstablishmentType $type): bool
    {
        if (! (bool) config('services.tiptop_pay.enabled')) {
            return false;
        }

        $merchant = $this->client->merchantForType($type);

        return $merchant !== null && $this->client->isConfigured($merchant);
    }

    public function appliesTo(Order $order): bool
    {
        $order->loadMissing('establishment');

        $merchant = $this->client->merchantForOrder($order);

        return $merchant !== null && $this->client->isConfigured($merchant);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function paymentPayload(Order $order): ?array
    {
        if (! $this->isEnabled() || ! $this->appliesTo($order)) {
            return null;
        }

        if ($order->payment_provider !== 'tiptop' && ! ($order->payment_status?->canCharge() ?? false)) {
            return null;
        }

        $merchant = $this->client->merchantForOrder($order);

        if ($merchant === null) {
            return null;
        }

        return [
            'provider' => 'tiptop',
            'required' => $order->payment_status?->canCharge() ?? false,
            'status' => $order->payment_status?->value ?? PaymentStatus::Pending->value,
            'public_id' => $this->client->publicId($merchant),
            'amount' => $order->total,
            'currency' => 'KZT',
            'invoice_id' => $this->invoiceId($order),
            'account_id' => (string) $order->user_id,
            'description' => $order->summary_title ?: ('Заказ '.$order->code),
            'transaction_id' => $order->tiptop_transaction_id,
        ];
    }

    /**
     * @return array{order: Order, payment: array<string, mixed>, three_ds?: array<string, mixed>}
     */
    public function charge(User $user, Order $order, string $cryptogram, ?string $cardHolderName, string $ipAddress): array
    {
        $this->assertOwnedPayableOrder($user, $order);

        $merchant = $this->requireMerchant($order);

        $payload = [
            'Amount' => $order->total,
            'Currency' => 'KZT',
            'IpAddress' => $ipAddress,
            'CardCryptogramPacket' => $cryptogram,
            'Name' => $cardHolderName,
            'InvoiceId' => $this->invoiceId($order),
            'Description' => $order->summary_title ?: ('Заказ '.$order->code),
            'AccountId' => (string) $user->id,
            'CultureName' => 'ru-RU',
        ];

        $jsonData = $this->receipts->jsonData($order);

        if ($jsonData !== null) {
            $payload['JsonData'] = $jsonData;
        }

        $response = $this->client->chargeCard($payload, $merchant);

        return $this->handleChargeResponse($order, $response);
    }

    /**
     * @return array{order: Order, payment: array<string, mixed>, three_ds?: array<string, mixed>}
     */
    public function complete3ds(User $user, Order $order, int $transactionId, string $paRes): array
    {
        $this->assertOwnedPayableOrder($user, $order);

        $merchant = $this->requireMerchant($order);

        $response = $this->client->post3ds([
            'TransactionId' => $transactionId,
            'PaRes' => $paRes,
        ], $merchant);

        return $this->handleChargeResponse($order, $response);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{code: int}
     */
    public function handleCheck(array $payload): array
    {
        $order = $this->findOrderFromNotification($payload);

        if ($order === null || ! $this->appliesTo($order)) {
            return ['code' => 10];
        }

        if (! $this->amountsMatch($order, $payload['Amount'] ?? null)) {
            return ['code' => 12];
        }

        if (! $order->payment_status?->canCharge()) {
            return ['code' => 13];
        }

        return ['code' => 0];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{code: int}
     */
    public function handlePay(array $payload): array
    {
        $order = $this->findOrderFromNotification($payload);

        if ($order === null || ! $this->appliesTo($order)) {
            return ['code' => 0];
        }

        if (! $this->amountsMatch($order, $payload['Amount'] ?? null)) {
            Log::warning('TipTop Pay: сумма Pay-уведомления не совпадает', [
                'order_id' => $order->id,
                'expected' => $order->total,
                'amount' => $payload['Amount'] ?? null,
            ]);

            return ['code' => 0];
        }

        $this->markPaid($order, $payload);

        return ['code' => 0];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{code: int}
     */
    public function handleFail(array $payload): array
    {
        $order = $this->findOrderFromNotification($payload);

        if ($order === null || ! $this->appliesTo($order)) {
            return ['code' => 0];
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return ['code' => 0];
        }

        $order->forceFill([
            'payment_status' => PaymentStatus::Failed,
            'tiptop_transaction_id' => isset($payload['TransactionId'])
                ? (int) $payload['TransactionId']
                : $order->tiptop_transaction_id,
            'payment_provider' => 'tiptop',
        ])->save();

        return ['code' => 0];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{code: int}
     */
    public function handleRefund(array $payload): array
    {
        $order = $this->findOrderFromNotification($payload);

        if ($order === null || ! $this->appliesTo($order)) {
            return ['code' => 0];
        }

        $amount = (int) round((float) ($payload['Amount'] ?? 0));
        $refundedTotal = min($order->total, (int) $order->payment_refunded_total + max(0, $amount));

        $order->forceFill([
            'payment_refunded_total' => $refundedTotal,
            'payment_status' => $refundedTotal >= $order->total
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded,
        ])->save();

        if ($refundedTotal >= $order->total) {
            $this->coffeeLoyalty->reverseForRefundedOrder($order);
        }

        return ['code' => 0];
    }

    public function refundOrderAmount(Order $order, int $amount): void
    {
        if ($amount < 1 || ! $this->appliesTo($order)) {
            return;
        }

        if (! $this->isEnabled() || $order->payment_provider !== 'tiptop' || blank($order->tiptop_transaction_id)) {
            return;
        }

        $remaining = max(0, $order->total - (int) $order->payment_refunded_total);

        if ($remaining < 1) {
            return;
        }

        $refundAmount = min($amount, $remaining);
        $merchant = $this->client->merchantForOrder($order);

        if ($merchant === null) {
            return;
        }

        $payload = [
            'TransactionId' => (int) $order->tiptop_transaction_id,
            'Amount' => $refundAmount,
        ];

        $jsonData = $this->receipts->refundJsonData($order, $refundAmount);

        if ($jsonData !== null) {
            $payload['JsonData'] = $jsonData;
        }

        $response = $this->client->refund($payload, $merchant);

        if (($response['Success'] ?? false) !== true) {
            throw new TipTopPayException(
                (string) ($response['Message'] ?? 'Не удалось вернуть оплату.'),
                422,
                'tiptop_refund_failed',
                $response,
            );
        }

        $refundedTotal = (int) $order->payment_refunded_total + $refundAmount;

        $order->forceFill([
            'payment_refunded_total' => $refundedTotal,
            'payment_status' => $refundedTotal >= $order->total
                ? PaymentStatus::Refunded
                : PaymentStatus::PartiallyRefunded,
        ])->save();

        if ($refundedTotal >= $order->total) {
            $this->coffeeLoyalty->reverseForRefundedOrder($order);
        }
    }

    /**
     * @param  array<string, mixed>  $gatewayPayload
     */
    public function markPaid(Order $order, array $gatewayPayload = []): Order
    {
        if ($order->payment_status === PaymentStatus::Paid && filled($order->paid_at)) {
            $this->syncGatewayFields($order, $gatewayPayload);

            return $order->fresh(['establishment', 'items.product', 'feedback', 'user']) ?? $order;
        }

        return DB::transaction(function () use ($order, $gatewayPayload) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->payment_status === PaymentStatus::Paid && filled($locked->paid_at)) {
                $this->syncGatewayFields($locked, $gatewayPayload);

                return $locked->fresh(['establishment', 'items.product', 'feedback', 'user']) ?? $locked;
            }

            $this->syncGatewayFields($locked, $gatewayPayload);

            $now = now();

            $locked->forceFill([
                'payment_status' => PaymentStatus::Paid,
                'payment_provider' => 'tiptop',
                'paid_at' => $locked->paid_at ?? $now,
                'accepted_at' => $locked->accepted_at ?? $now,
                'payment_label' => $this->paymentLabelFromGateway($gatewayPayload) ?? $locked->payment_label,
            ])->save();

            $this->finalizeExternalFulfillment($locked);
            $this->clearCartAfterPayment($locked);
            $this->coffeeLoyalty->awardForPaidOrder($locked);

            return $locked->fresh(['establishment', 'items.product', 'feedback', 'user']) ?? $locked;
        });
    }

    private function clearCartAfterPayment(Order $order): void
    {
        $cart = Cart::query()
            ->where('user_id', $order->user_id)
            ->where('establishment_id', $order->establishment_id)
            ->first();

        $cart?->items()->delete();
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{order: Order, payment: array<string, mixed>, three_ds?: array<string, mixed>}
     */
    private function handleChargeResponse(Order $order, array $response): array
    {
        $model = is_array($response['Model'] ?? null) ? $response['Model'] : [];

        if (($response['Success'] ?? false) === true) {
            $order = $this->markPaid($order, $model);

            return [
                'order' => $order,
                'payment' => $this->paymentPayload($order) ?? [],
            ];
        }

        if (filled($model['AcsUrl'] ?? null) && filled($model['PaReq'] ?? null)) {
            $order->forceFill([
                'payment_provider' => 'tiptop',
                'tiptop_transaction_id' => isset($model['TransactionId']) ? (int) $model['TransactionId'] : null,
            ])->save();

            return [
                'order' => $order->fresh(['establishment', 'items.product', 'feedback']) ?? $order,
                'payment' => $this->paymentPayload($order) ?? [],
                'three_ds' => [
                    'transaction_id' => (int) $model['TransactionId'],
                    'acs_url' => (string) $model['AcsUrl'],
                    'pa_req' => (string) $model['PaReq'],
                    'iframe_is_allowed' => (bool) ($model['IFrameIsAllowed'] ?? true),
                ],
            ];
        }

        $order->forceFill([
            'payment_status' => PaymentStatus::Failed,
            'payment_provider' => 'tiptop',
            'tiptop_transaction_id' => isset($model['TransactionId'])
                ? (int) $model['TransactionId']
                : $order->tiptop_transaction_id,
        ])->save();

        $message = (string) (
            $model['CardHolderMessage']
            ?? $response['Message']
            ?? 'Оплата отклонена.'
        );

        throw ValidationException::withMessages([
            'payment' => [$message],
        ]);
    }

    private function finalizeExternalFulfillment(Order $order): void
    {
        try {
            // Lazy resolve avoids a constructor cycle with OrderService.
            app(OrderService::class)->fulfillAfterPayment($order);
        } catch (Throwable $exception) {
            Log::error('TipTop Pay: не удалось финализировать заказ после оплаты', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $gatewayPayload
     */
    private function syncGatewayFields(Order $order, array $gatewayPayload): void
    {
        if ($gatewayPayload === []) {
            return;
        }

        $order->forceFill([
            'tiptop_transaction_id' => isset($gatewayPayload['TransactionId'])
                ? (int) $gatewayPayload['TransactionId']
                : $order->tiptop_transaction_id,
            'payment_label' => $this->paymentLabelFromGateway($gatewayPayload) ?? $order->payment_label,
            'payment_provider' => 'tiptop',
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $gatewayPayload
     */
    private function paymentLabelFromGateway(array $gatewayPayload): ?string
    {
        $type = trim((string) ($gatewayPayload['CardType'] ?? $gatewayPayload['Issuer'] ?? 'Card'));
        $lastFour = trim((string) ($gatewayPayload['CardLastFour'] ?? ''));

        if ($lastFour === '') {
            return $type !== '' ? $type : null;
        }

        return trim($type.' · '.$lastFour);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findOrderFromNotification(array $payload): ?Order
    {
        $invoiceId = $payload['InvoiceId'] ?? null;

        if (filled($invoiceId)) {
            $invoice = (string) $invoiceId;

            $order = Order::query()
                ->with('establishment')
                ->where('rink_external_order_id', $invoice)
                ->first();

            if ($order !== null) {
                return $order;
            }

            if (ctype_digit($invoice)) {
                $order = Order::query()->with('establishment')->find((int) $invoice);

                if ($order !== null) {
                    return $order;
                }
            }
        }

        $transactionId = $payload['TransactionId'] ?? null;

        if (filled($transactionId)) {
            return Order::query()
                ->with('establishment')
                ->where('tiptop_transaction_id', (int) $transactionId)
                ->first();
        }

        return null;
    }

    /**
     * Same value as rink API `external_order_id` for ticket orders; otherwise local order id.
     */
    public function invoiceId(Order $order): string
    {
        if (filled($order->rink_external_order_id)) {
            return (string) $order->rink_external_order_id;
        }

        return (string) $order->id;
    }

    private function amountsMatch(Order $order, mixed $amount): bool
    {
        if ($amount === null || $amount === '') {
            return false;
        }

        return abs((float) $amount - (float) $order->total) < 0.01;
    }

    private function assertOwnedPayableOrder(User $user, Order $order): void
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        if (! $this->appliesTo($order)) {
            throw ValidationException::withMessages([
                'payment' => ['TipTop Pay недоступен для этого заказа.'],
            ]);
        }

        if (! $order->payment_status?->canCharge()) {
            throw ValidationException::withMessages([
                'payment' => ['Этот заказ уже оплачен или недоступен для оплаты.'],
            ]);
        }
    }

    private function requireMerchant(Order $order): string
    {
        if (! (bool) config('services.tiptop_pay.enabled')) {
            throw ValidationException::withMessages([
                'payment' => ['Оплата через TipTop Pay отключена.'],
            ]);
        }

        $merchant = $this->client->merchantForOrder($order);

        if ($merchant === null || ! $this->client->isConfigured($merchant)) {
            throw ValidationException::withMessages([
                'payment' => ['Оплата через TipTop Pay отключена.'],
            ]);
        }

        return $merchant;
    }
}
