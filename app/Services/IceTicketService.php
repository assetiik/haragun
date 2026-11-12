<?php

namespace App\Services;

use App\Enums\ProductKind;
use App\Exceptions\RinkApiException;
use App\Exceptions\TipTopPayException;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\User;
use App\Services\RinkApi\RinkApiCatalog;
use App\Services\RinkApi\RinkApiClient;
use App\Services\TipTopPay\TipTopPayService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class IceTicketService
{
    public function __construct(
        private RinkApiCatalog $rink,
        private RinkApiClient $rinkClient,
        private TipTopPayService $tipTopPay,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function previewPromo(Establishment $establishment, string $code, string $date, string $time): array
    {
        if (! $this->rink->usesRink($establishment)) {
            throw ValidationException::withMessages([
                'code' => ['Промокоды недоступны для этого заведения.'],
            ]);
        }

        $session = $this->rink->resolveSession($date, $time);

        return $this->rink->promoPrices($code, $session['rink_session_id']);
    }

    /**
     * @return array{order: Order, ticket: array<string, mixed>, refunded_amount: int}
     */
    public function refund(User $user, Order $order, string $barcode, ?string $reason = null): array
    {
        $this->assertOwnedTicketOrder($user, $order);
        $this->assertBarcodeOnOrder($order, $barcode);

        if (! filled($order->rink_order_id) || ! $this->rinkClient->isConfigured()) {
            throw ValidationException::withMessages([
                'barcode' => ['Возврат через билетную систему недоступен для этого заказа.'],
            ]);
        }

        $payload = [];

        if (filled($reason)) {
            $payload['reason'] = $reason;
        }

        $ticket = $this->rinkClient->refundTicket($barcode, $payload);
        $refundedAmount = (int) ($ticket['price'] ?? $this->localPassPrice($order, $barcode));

        try {
            $this->tipTopPay->refundOrderAmount($order, $refundedAmount);
        } catch (TipTopPayException $exception) {
            Log::error('TipTop Pay: не удалось вернуть деньги за билет', [
                'order_id' => $order->id,
                'barcode' => $barcode,
                'amount' => $refundedAmount,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'barcode' => ['Билет возвращён в кассе, но возврат оплаты не выполнен. Обратитесь в поддержку.'],
            ]);
        } catch (Throwable $exception) {
            Log::error('TipTop Pay: неожиданная ошибка возврата', [
                'order_id' => $order->id,
                'barcode' => $barcode,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'barcode' => ['Билет возвращён в кассе, но возврат оплаты не выполнен. Обратитесь в поддержку.'],
            ]);
        }

        $order->load(['establishment', 'items.product', 'feedback']);

        return [
            'order' => $order->fresh(['establishment', 'items.product', 'feedback']) ?? $order,
            'ticket' => $ticket,
            'refunded_amount' => $refundedAmount,
        ];
    }

    /**
     * @return array{order: Order, ticket: array<string, mixed>}
     */
    public function move(User $user, Order $order, string $barcode, string $date, string $time): array
    {
        $this->assertOwnedTicketOrder($user, $order);
        $this->assertBarcodeOnOrder($order, $barcode);

        if (! filled($order->rink_order_id) || ! $this->rink->usesRink($order->establishment)) {
            throw ValidationException::withMessages([
                'barcode' => ['Перенос через билетную систему недоступен для этого заказа.'],
            ]);
        }

        $session = $this->rink->resolveSession($date, $time);
        $ticket = $this->rinkClient->moveTicket($barcode, [
            'session_id' => $session['rink_session_id'],
        ]);

        if ($this->orderHasSingleActiveTicket($order, $barcode)) {
            $entryOpens = $session['entry_opens_at'] ?? null;
            $order->forceFill([
                'rink_session_id' => $session['rink_session_id'],
                'session_date' => $date,
                'session_time' => $session['time'].':00',
                'session_ends_at' => $session['ends_at'].':00',
                'session_entry_opens_at' => is_string($entryOpens) && strlen($entryOpens) === 5
                    ? $entryOpens.':00'
                    : null,
            ])->save();
        }

        $order->load(['establishment', 'items.product', 'feedback']);

        return [
            'order' => $order->fresh(['establishment', 'items.product', 'feedback']),
            'ticket' => $ticket,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function remoteTicketsByCode(Order $order): array
    {
        if (! filled($order->rink_order_id) || ! $this->rinkClient->isConfigured()) {
            return [];
        }

        $map = [];

        foreach ($this->orderBarcodes($order) as $code) {
            try {
                $map[$code] = $this->rinkClient->ticket($code);
            } catch (RinkApiException) {
                continue;
            }
        }

        return $map;
    }

    private function assertOwnedTicketOrder(User $user, Order $order): void
    {
        if ($order->user_id !== $user->id) {
            abort(404);
        }

        if ($order->type->value !== 'tickets') {
            throw ValidationException::withMessages([
                'order' => ['Это не заказ билетов.'],
            ]);
        }
    }

    private function assertBarcodeOnOrder(Order $order, string $barcode): void
    {
        if (! in_array($barcode, $this->orderBarcodes($order), true)) {
            throw ValidationException::withMessages([
                'barcode' => ['Билет не найден в этом заказе.'],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function orderBarcodes(Order $order): array
    {
        $codes = [];

        foreach ($order->items as $item) {
            if ($item->kind !== ProductKind::Ticket->value || $item->is_discount) {
                continue;
            }

            foreach ($item->barcodes ?? [] as $code) {
                if (is_string($code) && $code !== '') {
                    $codes[] = $code;
                } elseif (is_array($code) && filled($code['code'] ?? null)) {
                    $codes[] = (string) $code['code'];
                }
            }
        }

        return $codes;
    }

    private function localPassPrice(Order $order, string $barcode): int
    {
        foreach ($order->items as $item) {
            if ($item->kind !== ProductKind::Ticket->value || $item->is_discount) {
                continue;
            }

            foreach ($item->barcodes ?? [] as $code) {
                $value = is_array($code) ? (string) ($code['code'] ?? '') : (string) $code;

                if ($value === $barcode) {
                    return (int) $item->unit_price;
                }
            }
        }

        return 0;
    }

    private function orderHasSingleActiveTicket(Order $order, string $barcode): bool
    {
        $codes = $this->orderBarcodes($order);

        return count($codes) === 1 && ($codes[0] ?? null) === $barcode;
    }
}
