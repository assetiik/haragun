<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'product_id',
    'kind',
    'name',
    'subtitle',
    'quantity',
    'unit_price',
    'amount',
    'sizes',
    'icon_key',
    'image_url',
    'is_discount',
    'sort_order',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
            'sizes' => 'array',
            'is_discount' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function amountLabel(): string
    {
        return Money::format($this->amount);
    }

    public function title(): string
    {
        if ($this->is_discount) {
            return $this->name;
        }

        $sizes = collect($this->sizes ?? [])
            ->map(fn ($size) => "р. {$size}")
            ->implode(', ');

        $base = "{$this->name} × {$this->quantity}";

        return $sizes !== '' ? "{$base} · {$sizes}" : $base;
    }
}
