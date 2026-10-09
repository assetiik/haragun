<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\Cart;
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
        $total = $this->total();
        $count = $this->itemsCount();

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
            'totals' => [
                'items_count' => $count,
                'total' => $total,
            ],
            'is_empty' => $this->isEmpty(),
        ];
    }
}
