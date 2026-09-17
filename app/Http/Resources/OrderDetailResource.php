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
            'guest_label' => $this->guestLabel(),
            'code' => $this->code,
            'title' => $this->titlePrefix(),
            'is_active' => $this->isActive(),
            'status_banner' => $this->statusBanner(),
            'timeline' => $this->timeline(),
            'coffee_screen' => $this->type === OrderType::Venue
                ? new OrderCoffeeScreenResource($this->resource)
                : null,
            'establishment' => [
                'id' => $this->establishment->id,
                'name' => $this->establishment->name,
                'address' => $this->establishment->address,
                'type' => $this->establishment->type->value,
                'distance_label' => $this->establishment->distanceLabel($latitude, $longitude),
                'location_meta' => $this->establishment->locationMeta($latitude, $longitude),
            ],
            'summary_title' => $this->summary_title,
            'summary_short' => $this->summary_short,
            'total' => $this->total,
            'total_label' => $this->totalLabel(),
            'items_count' => $this->items_count,
            'items' => OrderItemResource::collection($productItems),
            'discounts' => OrderItemResource::collection($this->items->where('is_discount', true)->values()),
            'payment' => [
                'amount_label' => $this->totalLabel(),
                'label' => $this->payment_label,
                'text' => $this->totalLabel().' · оплачено '.$this->payment_label,
            ],
            'session' => $this->type === OrderType::Tickets ? [
                'date' => $this->session_date?->toDateString(),
                'time' => $this->formattedSessionTime(),
                'ends_at' => $this->formattedSessionEndsAt(),
                'label' => $this->whenLabel(),
            ] : null,
            'ordered_at' => $this->paid_at?->timezone(config('app.timezone'))->translatedFormat('j F Y, H:i'),
            'points_note' => $this->points_to_earn > 0
                ? 'Начислим '.$this->points_to_earn.' баллов после '.($this->type === OrderType::Tickets ? 'визита' : 'выдачи')
                : null,
            'cancellation_note' => $this->type === OrderType::Tickets
                ? 'Бесплатно до 14:00 · возврат за три дня'
                : null,
            'pickup' => [
                'code' => $this->code,
                'label' => $this->type === OrderType::Tickets ? 'Код брони' : 'Код выдачи',
                'note' => $this->type === OrderType::Tickets
                    ? 'Назовите код на стойке проката'
                    : 'Подойдите к стойке с кодом',
            ],
            'actions' => [
                ['type' => 'receipt', 'label' => 'Чек'],
                [
                    'type' => $this->type === OrderType::Tickets ? 'rebook' : 'repeat',
                    'label' => $this->type === OrderType::Tickets ? 'Забронировать снова' : 'Повторить заказ',
                ],
            ],
        ];
    }

    private function whenLabel(): ?string
    {
        if ($this->session_date === null) {
            return null;
        }

        $weekdays = [0 => 'Воскресенье', 1 => 'Понедельник', 2 => 'Вторник', 3 => 'Среда', 4 => 'Четверг', 5 => 'Пятница', 6 => 'Суббота'];
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
            7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        $date = $this->session_date;

        return sprintf(
            '%s, %d %s · %s – %s',
            $weekdays[$date->dayOfWeek],
            $date->day,
            $months[$date->month],
            $this->formattedSessionTime(),
            $this->formattedSessionEndsAt(),
        );
    }
}
