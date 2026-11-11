<?php

namespace App\Http\Resources;

use App\Enums\OrderType;
use App\Enums\ProductKind;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
            'promo_code' => $this->promo_code,
            'promo_discount' => (int) ($this->promo_discount ?? 0),
            'points_to_earn' => $this->points_to_earn,
            'session' => $this->type === OrderType::Tickets ? [
                'date' => $this->session_date?->toDateString(),
                'time' => $this->formattedSessionTime(),
                'ends_at' => $this->formattedSessionEndsAt(),
                'entry_opens_at' => $this->passOpensAt()?->format('H:i'),
            ] : null,
            'passes' => $this->passes($request),
            'placed_at' => $this->placedAt()->toIso8601String(),
            'actions' => [
                'receipt',
                $this->type === OrderType::Tickets ? 'rebook' : 'repeat',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function passes(Request $request): array
    {
        if ($this->type !== OrderType::Tickets) {
            return [];
        }

        $validFrom = $this->passOpensAt();
        $validUntil = $this->sessionEndsAt();
        $windowOpen = $validFrom !== null
            && $validUntil !== null
            && now()->gte($validFrom)
            && now()->lte($validUntil);

        /** @var array<string, array<string, mixed>> $remoteTickets */
        $remoteTickets = $request->attributes->get('rink_tickets', []);
        if (! is_array($remoteTickets)) {
            $remoteTickets = [];
        }

        $passes = [];

        foreach ($this->items->where('is_discount', false) as $item) {
            if ($item->kind !== ProductKind::Ticket->value) {
                continue;
            }

            $codes = $this->itemBarcodes($item);

            foreach ($codes as $index => $code) {
                $remote = is_string($code) && $code !== '' ? ($remoteTickets[$code] ?? null) : null;
                $status = is_array($remote) ? (string) ($remote['status'] ?? 'active') : (filled($code) ? 'active' : 'pending');
                $canRefund = is_array($remote)
                    ? (bool) ($remote['can_refund'] ?? false)
                    : ($status === 'active');
                $canMove = is_array($remote)
                    ? (bool) ($remote['can_move'] ?? false)
                    : ($status === 'active');

                $sessionDate = $this->session_date?->toDateString();
                $sessionTime = $this->formattedSessionTime();
                $sessionEnds = $this->formattedSessionEndsAt();
                $entryOpens = $this->passOpensAt()?->format('H:i');

                if (is_array($remote) && is_array($remote['session'] ?? null)) {
                    $remoteSession = $remote['session'];
                    $sessionDate = isset($remoteSession['starts_at'])
                        ? Carbon::parse((string) $remoteSession['starts_at'])->toDateString()
                        : $sessionDate;
                    $sessionTime = isset($remoteSession['starts_at'])
                        ? Carbon::parse((string) $remoteSession['starts_at'])->format('H:i')
                        : $sessionTime;
                    $sessionEnds = isset($remoteSession['ends_at'])
                        ? Carbon::parse((string) $remoteSession['ends_at'])->format('H:i')
                        : $sessionEnds;
                    $entryOpens = isset($remoteSession['entry_opens_at'])
                        ? Carbon::parse((string) $remoteSession['entry_opens_at'])->format('H:i')
                        : $entryOpens;
                }

                $isActive = $status === 'active' && $windowOpen && filled($code);

                $passes[] = [
                    'id' => $item->id.'-'.$index,
                    'item_id' => $item->id,
                    'label' => $item->name,
                    'barcode' => $code,
                    'status' => $status,
                    'ticket_type' => is_array($remote) ? ($remote['ticket_type'] ?? null) : null,
                    'price' => is_array($remote) ? (int) ($remote['price'] ?? $item->unit_price) : $item->unit_price,
                    'full_price' => is_array($remote) ? (int) ($remote['full_price'] ?? $item->unit_price) : $item->unit_price,
                    'can_refund' => $canRefund && $status === 'active',
                    'can_move' => $canMove && $status === 'active',
                    'session' => [
                        'date' => $sessionDate,
                        'time' => $sessionTime,
                        'ends_at' => $sessionEnds,
                        'entry_opens_at' => $entryOpens,
                    ],
                    'valid_from' => $validFrom?->toIso8601String(),
                    'valid_until' => $validUntil?->toIso8601String(),
                    'is_active' => $isActive,
                ];
            }
        }

        return $passes;
    }

    /**
     * @return list<string|null>
     */
    private function itemBarcodes(OrderItem $item): array
    {
        $codes = [];

        foreach (array_values($item->barcodes ?? []) as $code) {
            if (is_string($code)) {
                $codes[] = $code;

                continue;
            }

            if (is_array($code) && array_key_exists('code', $code)) {
                $codes[] = $code['code'] !== null ? (string) $code['code'] : null;
            }
        }

        if ($codes !== []) {
            return $codes;
        }

        return array_fill(0, max(1, (int) $item->quantity), null);
    }
}
