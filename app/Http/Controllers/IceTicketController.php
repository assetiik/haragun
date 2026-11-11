<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveTicketPassRequest;
use App\Http\Requests\PreviewPromoCodeRequest;
use App\Http\Requests\RefundTicketPassRequest;
use App\Http\Resources\OrderDetailResource;
use App\Models\Establishment;
use App\Models\Order;
use App\Services\IceTicketService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Ice tickets', weight: 8)]
class IceTicketController extends Controller
{
    public function __construct(private IceTicketService $tickets) {}

    public function previewPromo(
        PreviewPromoCodeRequest $request,
        Establishment $establishment,
        string $code,
    ): JsonResponse {
        abort_unless($establishment->is_active, 404);

        $validated = $request->validated();
        $promo = $this->tickets->previewPromo(
            $establishment,
            $code,
            $validated['date'],
            $validated['time'],
        );

        return response()->json([
            'promo' => [
                'code' => $promo['code'],
                'name' => $promo['name'],
                'kind' => $promo['kind'],
                'value' => $promo['value'],
                'remaining' => $promo['remaining'],
                'session_id' => $promo['session_id'],
                'prices' => $promo['prices'],
            ],
        ]);
    }

    public function refund(
        RefundTicketPassRequest $request,
        Order $order,
        string $barcode,
    ): JsonResponse {
        $result = $this->tickets->refund(
            $request->user(),
            $order,
            $barcode,
            $request->validated('reason'),
        );

        $request->attributes->set('rink_tickets', $this->tickets->remoteTicketsByCode($result['order']));

        return response()->json([
            'refunded_amount' => $result['refunded_amount'],
            'ticket' => [
                'code' => $result['ticket']['code'] ?? $barcode,
                'status' => $result['ticket']['status'] ?? 'refunded',
                'can_refund' => (bool) ($result['ticket']['can_refund'] ?? false),
                'can_move' => (bool) ($result['ticket']['can_move'] ?? false),
            ],
            'order' => new OrderDetailResource($result['order']),
            'message' => 'Билет возвращён. Деньги вернутся тем же способом оплаты.',
        ]);
    }

    public function move(
        MoveTicketPassRequest $request,
        Order $order,
        string $barcode,
    ): JsonResponse {
        $validated = $request->validated();
        $result = $this->tickets->move(
            $request->user(),
            $order,
            $barcode,
            $validated['date'],
            $validated['time'],
        );

        $request->attributes->set('rink_tickets', $this->tickets->remoteTicketsByCode($result['order']));

        return response()->json([
            'ticket' => [
                'code' => $result['ticket']['code'] ?? $barcode,
                'status' => $result['ticket']['status'] ?? 'active',
                'session_id' => $result['ticket']['session_id'] ?? null,
                'can_refund' => (bool) ($result['ticket']['can_refund'] ?? true),
                'can_move' => (bool) ($result['ticket']['can_move'] ?? true),
            ],
            'order' => new OrderDetailResource($result['order']),
            'message' => 'Билет перенесён. QR-код не изменился.',
        ]);
    }
}
