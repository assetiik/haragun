<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'size', 'is_available'])]
class SkateSize extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::saved(function (SkateSize $size): void {
            $size->product?->syncSizeRangeFromSkateSizes();
        });

        static::deleted(function (SkateSize $size): void {
            $size->product?->syncSizeRangeFromSkateSizes();
        });
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('size');
    }
}
