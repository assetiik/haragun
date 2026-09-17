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
            'title' => $this->titlePrefix(),
            'is_active' => $isActive,
            'establishment' => [
                'id' => $establishment->id,
                'name' => $establishment->name,
                'address' => $establishment->address,
                'type' => $establishment->type->value,
            ],
            'total' => $this->total,
            'total_label' => $this->totalLabel(),
            'items_count' => $this->items_count,
            'summary_title' => $this->summary_title,
            'summary_short' => $this->summary_short,
            'payment_label' => $this->payment_label,
            'footer_label' => $this->items_count.' поз. · оплачено картой '.($this->cardTail() ?? ''),
        ];

        if ($this->type === OrderType::Venue) {
            $payload['timeline'] = $this->timeline();
            $payload['primary_action'] = [
                'type' => 'pickup_code',
                'label' => 'Код выдачи',
                'code' => $this->code,
            ];
            $payload['history_action'] = [
                'type' => 'repeat',
                'label' => 'Повторить',
            ];
        } else {
            $startsAt = $this->sessionStartsAt();
            $payload['session'] = [
                'date' => $this->session_date?->toDateString(),
                'date_label' => $this->sessionDateLabel(),
                'time' => $this->formattedSessionTime(),
                'ends_at' => $this->formattedSessionEndsAt(),
                'time_range' => $this->formattedSessionTime().' – '.$this->formattedSessionEndsAt(),
                'badge' => $startsAt?->isToday()
                    ? 'Сегодня в '.$this->formattedSessionTime()
                    : $this->sessionDateLabel(),
            ];
            $payload['guests_label'] = $this->guestsLabel();
            $payload['equipment'] = $this->equipmentBadges();
            $payload['booking_note'] = 'Назовите код на стойке проката';
            $payload['primary_action'] = [
                'type' => 'booking_code',
                'label' => 'Код брони',
                'code' => $this->code,
            ];
            $payload['history_action'] = [
                'type' => 'rebook',
                'label' => 'Забронировать снова',
            ];
            $payload['status_banner'] = $this->statusBanner();
        }

        return $payload;
    }

    private function sessionDateLabel(): ?string
    {
        if ($this->session_date === null) {
            return null;
        }

        $weekdays = [0 => 'воскресенье', 1 => 'понедельник', 2 => 'вторник', 3 => 'среда', 4 => 'четверг', 5 => 'пятница', 6 => 'суббота'];
        $months = [1 => 'янв', 2 => 'фев', 3 => 'мар', 4 => 'апр', 5 => 'мая', 6 => 'июн', 7 => 'июл', 8 => 'авг', 9 => 'сен', 10 => 'окт', 11 => 'ноя', 12 => 'дек'];

        $date = $this->session_date;

        return $date->day.' '.$months[$date->month].', '.$weekdays[$date->dayOfWeek];
    }

    private function guestsLabel(): string
    {
        $tickets = $this->items
            ->where('kind', ProductKind::Ticket->value)
            ->where('is_discount', false);

        $adult = $tickets->first(fn ($item) => str_contains(mb_strtolower($item->name), 'взросл'));
        $child = $tickets->first(fn ($item) => str_contains(mb_strtolower($item->name), 'детск'));

        $parts = [];
        if ($adult) {
            $parts[] = $adult->quantity.' '.($adult->quantity === 1 ? 'взрослый' : 'взрослых');
        }
        if ($child) {
            $parts[] = $child->quantity.' '.($child->quantity === 1 ? 'детский' : 'детских');
        }

        return $parts === [] ? $this->summary_short ?? '' : implode(', ', $parts);
    }

    /**
     * @return list<array{label: string, icon_key: string|null}>
     */
    private function equipmentBadges(): array
    {
        $badges = [];

        foreach ($this->items->where('kind', ProductKind::RentalSkates->value) as $item) {
            foreach ($item->sizes ?? [] as $size) {
                $badges[] = [
                    'label' => "Коньки {$size}",
                    'icon_key' => 'skates',
                ];
            }
        }

        foreach ($this->items->where('kind', ProductKind::RentalHelmet->value) as $item) {
            $badges[] = [
                'label' => "Шлем × {$item->quantity}",
                'icon_key' => 'helmet',
            ];
        }

        return $badges;
    }
}
