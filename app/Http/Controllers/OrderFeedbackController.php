<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackRating;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Requests\StoreOrderFeedbackRequest;
use App\Http\Resources\OrderCoffeeScreenResource;
use App\Http\Resources\OrderDetailResource;
use App\Models\Order;
use App\Models\OrderFeedback;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Orders', weight: 7)]
class OrderFeedbackController extends Controller
{
    public function screen(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        abort_unless($order->type === OrderType::Venue, 404);

        $order->load(['items.product', 'feedback', 'establishment']);

        return response()->json([
            'order' => new OrderDetailResource($order),
            'coffee_screen' => new OrderCoffeeScreenResource($order),
        ]);
    }

    public function store(StoreOrderFeedbackRequest $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        abort_unless($order->type === OrderType::Venue, 404);

        if (! in_array($order->status, [OrderStatus::Ready, OrderStatus::Completed], true)) {
            throw ValidationException::withMessages([
                'order' => ['Оценить можно после выдачи заказа.'],
            ]);
        }

        if ($order->feedback()->exists()) {
            throw ValidationException::withMessages([
                'order' => ['Вы уже оценили этот заказ.'],
            ]);
        }

        $validated = $request->validated();

        $feedback = OrderFeedback::query()->create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'rating' => FeedbackRating::from($validated['rating']),
            'likes' => $validated['likes'] ?? [],
            'dislikes' => $validated['dislikes'] ?? [],
            'comment' => $validated['comment'] ?? null,
        ]);

        if ($order->status === OrderStatus::Ready) {
            $order->update([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
            ]);
        }

        $order->load(['items.product', 'feedback', 'establishment']);

        return response()->json([
            'feedback' => [
                'id' => $feedback->id,
                'rating' => $feedback->rating->value,
                'likes' => array_values($feedback->likes ?? []),
                'dislikes' => array_values($feedback->dislikes ?? []),
                'comment' => $feedback->comment,
            ],
            'coffee_screen' => new OrderCoffeeScreenResource($order),
        ], 201);
    }

    public function markReady(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOwner($request, $order);
        abort_unless($order->type === OrderType::Venue, 404);

        $order->update([
            'status' => OrderStatus::Ready,
            'preparing_at' => $order->preparing_at ?? now(),
            'ready_at' => now(),
        ]);

        $order->load(['items.product', 'feedback', 'establishment']);

        return response()->json([
            'order' => new OrderDetailResource($order),
            'coffee_screen' => new OrderCoffeeScreenResource($order),
        ]);
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
