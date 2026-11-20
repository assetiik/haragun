<?php

namespace App\Services;

use App\Enums\ProductKind;
use App\Exceptions\RinkApiException;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\User;
use App\Services\RinkApi\RinkApiCatalog;
use App\Services\RinkApi\RinkApiClient;
use Illuminate\Validation\ValidationException;

class IceTicketService
{
    public function __construct(
        private RinkApiCatalog $rink,
        private RinkApiClient $rinkClient,
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
     * Guest refunds are disabled: rink staff only (19 Nov 2026).
     *
     * @return never
     */
    public function refund(User $user, Order $order, string $barcode, ?string $reason = null): array
    {
        $this->assertOwnedTicketOrder($user, $order);
        $this->assertBarcodeOnOrder($order, $barcode);

        throw $this->staffOnlyException(
            $order,
            $barcode,
            'refund_by_staff_only',
            'Возврат билета оформляют сотрудники катка.',
        );
    }

    /**
     * Guest moves are disabled: rink staff only (19 Nov 2026).
     *
     * @return never
     */
    public function move(User $user, Order $order, string $barcode, string $date, string $time): array
    {
        $this->assertOwnedTicketOrder($user, $order);
        $this->assertBarcodeOnOrder($order, $barcode);

        throw $this->staffOnlyException(
            $order,
            $barcode,
            'move_by_staff_only',
            'Перенос билета оформляют сотрудники катка.',
        );
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

    private function staffOnlyException(
        Order $order,
        string $barcode,
        string $errorCode,
        string $fallbackPrefix,
    ): RinkApiException {
        $staffContact = $this->staffContactForBarcode($order, $barcode);

        $message = filled($staffContact)
            ? "Чтобы вернуть или перенести билет, свяжитесь с катком: {$staffContact}"
            : 'Чтобы вернуть или перенести билет, обратитесь к администратору катка.';

        return new RinkApiException(
            $message,
            403,
            $errorCode,
            [
                'staff_contact' => $staffContact,
                'hint' => $fallbackPrefix,
            ],
            "/tickets/{$barcode}",
        );
    }

    private function staffContactForBarcode(Order $order, string $barcode): string
    {
        if (! $this->rinkClient->isConfigured()) {
            return '';
        }

        try {
            $ticket = $this->rinkClient->ticket($barcode);

            return is_string($ticket['staff_contact'] ?? null)
                ? trim((string) $ticket['staff_contact'])
                : '';
        } catch (RinkApiException) {
            return '';
        }
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
}
