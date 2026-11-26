<?php

namespace App\Http\Controllers;

use App\Enums\EstablishmentType;
use App\Enums\PaymentMethod;
use App\Http\Requests\AddCartItemRequest;
use App\Http\Requests\CheckoutCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\OrderCoffeeScreenResource;
use App\Http\Resources\OrderDetailResource;
use App\Models\CartItem;
use App\Models\Establishment;
use App\Services\CartService;
use App\Services\TipTopPay\TipTopPayService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Cart', weight: 6)]
class CartController extends Controller
{
    public function __construct(
        private CartService $carts,
        private TipTopPayService $payments,
    ) {}

    public function show(Request $request, Establishment $establishment): JsonResponse
    {
        $cart = $this->carts->load(
            $this->carts->getOrCreate($request->user(), $establishment)
        );

        return (new CartResource($cart))->response()->setStatusCode(200);
    }

    public function addItem(AddCartItemRequest $request, Establishment $establishment): JsonResponse
    {
        $cart = $this->carts->getOrCreate($request->user(), $establishment);
        $cart = $this->carts->addItem($cart, $request->validated());

        return (new CartResource($cart))->response()->setStatusCode(200);
    }

    public function updateItem(
        UpdateCartItemRequest $request,
        Establishment $establishment,
        CartItem $cartItem,
    ): JsonResponse {
        $cart = $this->carts->getOrCreate($request->user(), $establishment);
        $cart = $this->carts->updateQuantity($cart, $cartItem, (int) $request->validated('quantity'));

        return (new CartResource($cart))->response()->setStatusCode(200);
    }

    public function removeItem(Request $request, Establishment $establishment, CartItem $cartItem): JsonResponse
    {
        $cart = $this->carts->getOrCreate($request->user(), $establishment);
        $cart = $this->carts->removeItem($cart, $cartItem);

        return (new CartResource($cart))->response()->setStatusCode(200);
    }

    public function clear(Request $request, Establishment $establishment): JsonResponse
    {
        $cart = $this->carts->getOrCreate($request->user(), $establishment);
        $cart = $this->carts->clear($cart);

        return (new CartResource($cart))->response()->setStatusCode(200);
    }

    public function update(Request $request, Establishment $establishment): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required_without:apply_welcome_gift', 'string', 'in:kaspi'],
            'apply_welcome_gift' => ['required_without:payment_method', 'boolean'],
        ]);

        $cart = $this->carts->getOrCreate($request->user(), $establishment);

        if (array_key_exists('payment_method', $validated)) {
            $cart = $this->carts->setPaymentMethod(
                $cart,
                PaymentMethod::from($validated['payment_method']),
            );
        }

        if (array_key_exists('apply_welcome_gift', $validated)) {
            $cart = $this->carts->setApplyWelcomeGift($cart, (bool) $validated['apply_welcome_gift']);
        }

        return (new CartResource($cart))->response()->setStatusCode(200);
    }

    public function checkout(CheckoutCartRequest $request, Establishment $establishment): JsonResponse
    {
        $cart = $this->carts->getOrCreate($request->user(), $establishment);
        $result = $this->carts->checkout($cart, $request->validated('payment_label'));
        $payment = $this->payments->paymentPayload($result['order']);
        $awaitingPayment = is_array($payment) && ($payment['required'] ?? false);
        $order = $result['order']->loadMissing('establishment');

        return response()->json([
            'order' => new OrderDetailResource($order),
            'payment' => $payment,
            // Coffee post-checkout screen is only for standalone coffee shops.
            'coffee_screen' => (! $awaitingPayment && $order->establishment?->type === EstablishmentType::Coffee)
                ? new OrderCoffeeScreenResource($order)
                : null,
            'cart' => new CartResource($result['cart']),
        ], 201);
    }
}
