<?php

namespace App\Models;

use App\Support\Money;
use Database\Factories\ProductSizeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'code',
    'label',
    'volume_ml',
    'price',
    'is_default',
    'sort_order',
])]
class ProductSize extends Model
{
    /** @use HasFactory<ProductSizeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'volume_ml' => 'integer',
            'price' => 'integer',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::saving(function (ProductSize $size): void {
            if (blank($size->label) && $size->volume_ml !== null) {
                $size->label = $size->volume_ml.' мл';
            }
        });

        static::saved(function (ProductSize $size): void {
            $size->product?->syncPriceFromDefaultSize();
        });

        static::deleted(function (ProductSize $size): void {
            $size->product?->syncPriceFromDefaultSize();
        });
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    public function priceLabel(): string
    {
        return Money::format($this->price);
    }

    public function buttonLabel(): string
    {
        return "{$this->code}\n{$this->label}";
    }
}
