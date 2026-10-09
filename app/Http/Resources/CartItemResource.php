<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Models\Modifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $modifiers = $this->resolvedModifiers();

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->product->name,
            'image_url' => $this->product->publicImageUrl(),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount(),
            'size' => $this->size ? [
                'id' => $this->size->id,
                'code' => $this->size->code,
                'label' => $this->size->label,
                'volume_ml' => $this->size->volume_ml,
            ] : null,
            'modifiers' => $modifiers->map(fn (Modifier $modifier) => [
                'id' => $modifier->id,
                'name' => $modifier->name,
                'price' => $modifier->price,
            ])->values(),
        ];
    }
}
