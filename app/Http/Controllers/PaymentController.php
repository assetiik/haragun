<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChargeOrderPaymentRequest;
use App\Http\Requests\CompleteOrder3dsRequest;
use App\Http\Resources\OrderDetailResource;
use App\Models\Order;
use App\Services\TipTopPay\TipTopPayService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Payments', weight: 9)]
class PaymentController extends Controller
{
    public function __construct(private TipTopPayService $payments) {}

    public function charge(ChargeOrderPaymentRequest $request, Order $order): JsonResponse
    {
        $result = $this->payments->charge(
            $request->user(),
            $order,
            $request->validated('cryptogram'),
            $request->validated('name'),
            (string) $request->ip(),
        );

        $payload = [
            'order' => new OrderDetailResource($result['order']),
            'payment' => $result['payment'],
        ];

        if (isset($result['three_ds'])) {
            $payload['three_ds'] = $result['three_ds'];
        }

        return response()->json($payload);
    }

    public function complete3ds(CompleteOrder3dsRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->payments->complete3ds(
            $request->user(),
            $order,
            (int) $validated['transaction_id'],
            (string) $validated['pa_res'],
        );

        $payload = [
            'order' => new OrderDetailResource($result['order']),
            'payment' => $result['payment'],
        ];

        if (isset($result['three_ds'])) {
            $payload['three_ds'] = $result['three_ds'];
        }

        return response()->json($payload);
    }
}
