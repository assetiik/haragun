<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChargeOrderPaymentRequest;
use App\Http\Requests\CompleteOrder3dsRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\OrderCoffeeScreenResource;
use App\Http\Resources\OrderDetailResource;
use App\Models\Order;
use App\Services\CartService;
use App\Services\TipTopPay\TipTopPayService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Payments', weight: 9)]
class PaymentController extends Controller
{
    public function __construct(
        private TipTopPayService $payments,
        private CartService $carts,
    ) {}

    public function charge(ChargeOrderPaymentRequest $request, Order $order): JsonResponse
    {
        $result = $this->payments->charge(
            $request->user(),
            $order,
            $request->validated('cryptogram'),
            $request->validated('name'),
            (string) $request->ip(),
        );

        return response()->json($this->paymentResponse($result));
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

        return response()->json($this->paymentResponse($result));
    }

    /**
     * @param  array{order: Order, payment: array<string, mixed>, three_ds?: array<string, mixed>}  $result
     * @return array<string, mixed>
     */
    private function paymentResponse(array $result): array
    {
        $order = $result['order']->loadMissing(['establishment', 'items.product', 'feedback', 'user']);
        $paid = ! ($result['payment']['required'] ?? true);

        $payload = [
            'order' => new OrderDetailResource($order),
            'payment' => $result['payment'],
            'coffee_screen' => $paid ? new OrderCoffeeScreenResource($order) : null,
            'cart' => $order->establishment !== null && $order->user !== null
                ? new CartResource($this->carts->load(
                    $this->carts->getOrCreate($order->user, $order->establishment)
                ))
                : null,
        ];

        if (isset($result['three_ds'])) {
            $payload['three_ds'] = $result['three_ds'];
        }

        return $payload;
    }
}
