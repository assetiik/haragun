<?php

namespace App\Http\Resources;

use App\Enums\FeedbackRating;
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

        $payload = [
            'screen' => $screen,
            'guest_number' => $this->guest_number ?? $this->number,
            'pickup_number' => $this->number,
            'items' => $items,
            'hero_image_url' => $items->first()['image_url'] ?? null,
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
                'ratings' => [
                    FeedbackRating::Positive->value,
                    FeedbackRating::Negative->value,
                ],
                'likes' => array_map(
                    fn (FeedbackTag $tag): string => $tag->value,
                    FeedbackTag::likeOptions(),
                ),
                'dislikes' => array_map(
                    fn (FeedbackTag $tag): string => $tag->value,
                    FeedbackTag::dislikeOptions(),
                ),
                'comment_required' => false,
            ];
        }

        return $payload;
    }

    private function primaryAction(string $screen): string
    {
        return match ($screen) {
            'ready', 'rated' => 'close',
            default => 'dismiss',
        };
    }
}
