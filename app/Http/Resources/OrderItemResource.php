<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\StoredUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'kind' => $this->kind,
            'name' => $this->name,
            'subtitle' => $this->subtitle,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'amount' => $this->amount,
            'sizes' => $this->sizes ?? [],
            'icon_key' => $this->icon_key,
            'image_url' => StoredUrl::public($this->image_url),
            'is_discount' => $this->is_discount,
        ];
    }
}
