<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

#[Fillable([
    'cart_id',
    'product_id',
    'product_size_id',
    'modifier_ids',
    'signature',
    'quantity',
    'unit_price',
])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modifier_ids' => 'array',
            'quantity' => 'integer',
            'unit_price' => 'integer',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'product_size_id');
    }

    public function amount(): int
    {
        return $this->unit_price * $this->quantity;
    }

    public function amountLabel(): string
    {
        return Money::format($this->amount());
    }

    public function unitPriceLabel(): string
    {
        return Money::format($this->unit_price);
    }

    /**
     * @return Collection<int, Modifier>
     */
    public function resolvedModifiers(): Collection
    {
        return Modifier::query()
            ->whereIn('id', $this->modifier_ids ?? [])
            ->orderBy('sort_order')
            ->get();
    }

    public function subtitle(): string
    {
        $parts = [];

        if ($this->size !== null) {
            $parts[] = $this->size->label;
        } elseif (filled($this->product?->meta)) {
            $parts[] = $this->product->meta;
        }

        foreach ($this->resolvedModifiers() as $modifier) {
            $parts[] = $modifier->name;
        }

        if ($parts === [] && filled($this->product?->description)) {
            $parts[] = $this->product->description;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  list<int>  $modifierIds
     */
    public static function buildSignature(int $productId, ?int $sizeId, array $modifierIds): string
    {
        $ids = collect($modifierIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        return hash('sha256', json_encode([
            'product_id' => $productId,
            'size_id' => $sizeId,
            'modifier_ids' => $ids,
        ], JSON_THROW_ON_ERROR));
    }
}
