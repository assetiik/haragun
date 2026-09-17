<?php

namespace App\Http\Resources;

use App\Enums\FeedbackTag;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\StoredUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderCoffeeScreenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $screen = $this->coffeeScreenKey() ?? 'accepted';
        $items = $this->items
            ->where('is_discount', false)
            ->flatMap(function (OrderItem $item) {
                return collect(range(1, max(1, $item->quantity)))->map(fn () => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'image_url' => StoredUrl::public($item->image_url ?? $item->product?->image_url),
                    'subtitle' => $item->subtitle,
                ]);
            })
            ->values();

        $heroImage = $items->first()['image_url'] ?? null;

        $payload = [
            'screen' => $screen,
            'title' => 'Кофе',
            'guest_label' => $this->guestLabel(),
            'guest_number' => $this->guest_number ?? $this->number,
            'pickup_number' => $this->number,
            'pickup_number_label' => (string) $this->number,
            'message' => $this->coffeeMessage($screen),
            'items' => $items,
            'hero_image_url' => $heroImage,
            'primary_action' => $this->primaryAction($screen),
            'feedback' => null,
            'has_feedback' => $this->hasFeedback(),
        ];

        if (in_array($screen, ['ready', 'rated'], true) || $this->status === OrderStatus::Ready) {
            $payload['feedback'] = [
                'enabled' => ! $this->hasFeedback() && in_array($this->status, [
                    OrderStatus::Ready,
                    OrderStatus::Completed,
                ], true),
                'prompt' => 'Заказ готов. Вам понравился заказ?',
                'likes' => [
                    'label' => 'Что нравится?',
                    'options' => collect(FeedbackTag::likeOptions())->map(fn (FeedbackTag $tag) => [
                        'id' => $tag->value,
                        'label' => $tag->label(),
                    ])->values(),
                ],
                'dislikes' => [
                    'label' => 'Что смущает?',
                    'options' => collect(FeedbackTag::dislikeOptions())->map(fn (FeedbackTag $tag) => [
                        'id' => $tag->value,
                        'label' => $tag->label(),
                    ])->values(),
                ],
                'comment' => [
                    'required' => false,
                    'placeholder' => 'Как нам стать лучше?',
                ],
                'submit_label' => 'Отправить',
                'rate_label' => 'Оценить',
                'close_label' => 'Закрыть',
            ];
        }

        return $payload;
    }

    private function coffeeMessage(string $screen): string
    {
        return match ($screen) {
            'accepted' => 'Заказ принят и уже готовится. Заберите его на стойке выдачи по номеру',
            'ready' => 'Заказ готов. Вам понравился заказ?',
            'rated' => 'Спасибо за оценку!',
            default => 'Заказ принят и уже готовится. Заберите его на стойке выдачи по номеру',
        };
    }

    /**
     * @return array{type: string, label: string}
     */
    private function primaryAction(string $screen): array
    {
        return match ($screen) {
            'accepted' => ['type' => 'dismiss', 'label' => 'Понятно'],
            'ready' => ['type' => 'close', 'label' => 'Закрыть'],
            'rated' => ['type' => 'close', 'label' => 'Закрыть'],
            default => ['type' => 'dismiss', 'label' => 'Понятно'],
        };
    }
}
