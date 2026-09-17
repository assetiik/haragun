<?php

namespace App\Http\Controllers;

use App\Enums\OrderType;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderListResource;
use App\Models\Order;
use App\Services\OrderService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Orders', weight: 7)]
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:venue,tickets'],
        ]);

        $type = OrderType::from($validated['type'] ?? OrderType::Venue->value);
        $userId = $request->user()->id;

        $orders = Order::query()
            ->with(['establishment', 'items'])
            ->forUser($userId)
            ->ofType($type)
            ->latest('id')
            ->get();

        $active = $orders->filter(fn (Order $order) => $order->isActive())->values();
        $history = $orders->reject(fn (Order $order) => $order->isActive())->values();

        $counts = [
            OrderType::Venue->value => Order::query()->forUser($userId)->ofType(OrderType::Venue)->get()
                ->filter(fn (Order $order) => $order->isActive())->count(),
            OrderType::Tickets->value => Order::query()->forUser($userId)->ofType(OrderType::Tickets)->get()
                ->filter(fn (Order $order) => $order->isActive())->count(),
        ];

        return response()->json([
            'tabs' => [
                [
                    'key' => OrderType::Venue->value,
                    'title' => OrderType::Venue->tabTitle(),
                    'badge' => $counts[OrderType::Venue->value],
                ],
                [
                    'key' => OrderType::Tickets->value,
                    'title' => OrderType::Tickets->tabTitle(),
                    'badge' => $counts[OrderType::Tickets->value],
                ],
            ],
            'selected_tab' => $type->value,
            'sections' => [
                [
                    'key' => 'active',
                    'title' => $type === OrderType::Tickets ? 'Ближайший сеанс' : 'Активный',
                    'orders' => OrderListResource::collection($active),
                ],
                [
                    'key' => 'history',
                    'title' => 'История',
                    'orders' => OrderListResource::collection($history),
                ],
            ],
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orders->create($request->user(), $request->validated());

        return response()->json([
            'message' => 'Заказ оформлен',
            'order' => new OrderDetailResource($order),
        ], 201);
    }

    public function show(Request $request, Order $order): OrderDetailResource
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load(['establishment', 'items.product', 'feedback']);

        return new OrderDetailResource($order);
    }
}
