<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Services\WelcomeCoffeeGiftService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $welcomeGifts = app(WelcomeCoffeeGiftService::class);
        $discount = $welcomeGifts->giftUnitDiscount($this->resource);
        $total = max(0, $this->total() - $discount);
        $count = $this->itemsCount();
        $giftPayload = $welcomeGifts->payloadFor($this->user);
        $canApply = ($giftPayload['available'] ?? false)
            && $welcomeGifts->cartHasEligibleEspresso($this->resource);

        return [
            'id' => $this->id,
            'establishment' => [
                'id' => $this->establishment->id,
                'name' => $this->establishment->name,
                'address' => $this->establishment->address,
            ],
            'items' => CartItemResource::collection($this->items),
            'payment' => [
                'selected_id' => $this->payment_method->value,
                'methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
                    'id' => $method->value,
                    'logo_key' => $method->logoKey(),
                ])->values(),
            ],
            'welcome_gift' => [
                'available' => (bool) ($giftPayload['available'] ?? false),
                'can_apply' => $canApply,
                'applied' => (bool) $this->apply_welcome_gift && $discount > 0,
                'title' => $giftPayload['title'] ?? WelcomeCoffeeGiftService::TITLE,
                'subtitle' => $giftPayload['subtitle'] ?? WelcomeCoffeeGiftService::SUBTITLE,
                'discount' => $discount,
            ],
            'totals' => [
                'items_count' => $count,
                'subtotal' => $this->total(),
                'welcome_gift_discount' => $discount,
                'total' => $total,
            ],
            'is_empty' => $this->isEmpty(),
        ];
    }
}
