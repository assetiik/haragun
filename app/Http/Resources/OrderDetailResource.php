<?php

namespace App\Http\Resources;

use App\Enums\OrderType;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $latitude = is_numeric($lat) ? (float) $lat : null;
        $longitude = is_numeric($lng) ? (float) $lng : null;

        $productItems = $this->items->where('is_discount', false)->values();

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'number' => $this->number,
            'guest_number' => $this->guest_number,
            'code' => $this->code,
            'is_active' => $this->isActive(),
            'timeline' => collect($this->timeline())->map(fn (array $step): array => [
                'key' => $step['key'],
                'state' => $step['state'],
                'time' => $step['time_label'],
            ])->all(),
            'coffee_screen' => $this->type === OrderType::Venue
                ? new OrderCoffeeScreenResource($this->resource)
                : null,
            'establishment' => [
                'id' => $this->establishment->id,
                'name' => $this->establishment->name,
                'address' => $this->establishment->address,
                'type' => $this->establishment->type->value,
                'distance_km' => $this->establishment->distanceKm($latitude, $longitude),
            ],
            'summary_title' => $this->summary_title,
            'summary_short' => $this->summary_short,
            'total' => $this->total,
            'items_count' => $this->items_count,
            'items' => OrderItemResource::collection($productItems),
            'discounts' => OrderItemResource::collection($this->items->where('is_discount', true)->values()),
            'payment_label' => $this->payment_label,
            'points_to_earn' => $this->points_to_earn,
            'session' => $this->type === OrderType::Tickets ? [
                'date' => $this->session_date?->toDateString(),
                'time' => $this->formattedSessionTime(),
                'ends_at' => $this->formattedSessionEndsAt(),
            ] : null,
            'placed_at' => $this->placedAt()->toIso8601String(),
            'actions' => [
                'receipt',
                $this->type === OrderType::Tickets ? 'rebook' : 'repeat',
            ],
        ];
    }
}
