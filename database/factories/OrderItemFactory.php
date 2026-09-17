<?php

namespace Database\Factories;

use App\Enums\ProductKind;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'kind' => ProductKind::Food->value,
            'name' => 'Капучино',
            'subtitle' => '250 мл',
            'quantity' => 1,
            'unit_price' => 1200,
            'amount' => 1200,
            'sizes' => null,
            'icon_key' => 'coffee',
            'image_url' => null,
            'is_discount' => false,
            'sort_order' => 1,
        ];
    }
}
