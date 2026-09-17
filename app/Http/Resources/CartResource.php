<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Support\Money;
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
            'title' => 'Оформить заказ',
            'establishment' => [
                'id' => $this->establishment->id,
                'name' => $this->establishment->name,
                'address' => $this->establishment->address,
            ],
            'header' => [
                'address' => $this->establishment->address,
                'pickup_label' => 'Забрать на стойке · 10–15 мин',
            ],
            'clear_confirm' => [
                'title' => 'Очистить корзину?',
                'message' => 'Все напитки будут удалены — заказ придется собрать заново.',
                'cancel_label' => 'Нет',
                'confirm_label' => 'Да',
            ],
            'items' => CartItemResource::collection($this->items),
            'payment' => [
                'label' => 'Способ оплаты',
                'selected_id' => $this->payment_method->value,
                'methods' => collect(PaymentMethod::cases())->map(fn (PaymentMethod $method) => [
                    'id' => $method->value,
                    'name' => $method->label(),
                    'logo_key' => $method->logoKey(),
                ])->values(),
            ],
            'totals' => [
                'items_count' => $count,
                'total' => $total,
                'total_label' => Money::format($total),
                'pay_button_label' => 'Оплатить '.Money::format($total),
            ],
            'is_empty' => $this->isEmpty(),
            'summary' => [
                'items_count' => $count,
                'total' => $total,
                'total_label' => Money::format($total),
                'label' => "Корзина · {$count} шт",
            ],
        ];
    }
}
