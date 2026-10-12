<?php

namespace App\Http\Resources;

use App\Enums\OrderType;
use App\Enums\ProductKind;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $establishment = $this->establishment;
        $isActive = $this->isActive();

        $payload = [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'number' => $this->number,
            'code' => $this->code,
            'is_active' => $isActive,
            'establishment' => [
                'id' => $establishment->id,
                'name' => $establishment->name,
                'address' => $establishment->address,
                'type' => $establishment->type->value,
            ],
            'total' => $this->total,
            'items_count' => $this->items_count,
            'summary_title' => $this->summary_title,
            'summary_short' => $this->summary_short,
            'payment_label' => $this->payment_label,
            'placed_at' => $this->placedAt()->toIso8601String(),
            'thumbnails' => $this->thumbnails(),
        ];

        if ($this->type === OrderType::Venue) {
            $payload['primary_action'] = 'pickup_code';
            $payload['history_action'] = 'repeat';
        } else {
            $payload['session'] = [
                'date' => $this->session_date?->toDateString(),
                'time' => $this->formattedSessionTime(),
                'ends_at' => $this->formattedSessionEndsAt(),
            ];
            $payload['equipment'] = $this->equipment();
            $payload['primary_action'] = 'booking_code';
            $payload['history_action'] = 'rebook';
        }

        return $payload;
    }

    /**
     * @return list<array{kind: string, size?: int|string, quantity?: int, icon_key: string}>
     */
    private function equipment(): array
    {
        $items = [];

        foreach ($this->items->where('kind', ProductKind::RentalSkates->value) as $item) {
            foreach ($item->sizes ?? [] as $size) {
                $items[] = [
                    'kind' => ProductKind::RentalSkates->value,
                    'size' => $size,
                    'icon_key' => 'skates',
                ];
            }
        }

        foreach ($this->items->where('kind', ProductKind::RentalHelmet->value) as $item) {
            $items[] = [
                'kind' => ProductKind::RentalHelmet->value,
                'quantity' => $item->quantity,
                'icon_key' => 'helmet',
            ];
        }

        foreach ($this->items->where('kind', ProductKind::RentalSupport->value) as $item) {
            $items[] = [
                'kind' => ProductKind::RentalSupport->value,
                'quantity' => $item->quantity,
                'icon_key' => 'support',
            ];
        }

        return $items;
    }
}
