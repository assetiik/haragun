<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\TipTopPay\TipTopPayReceiptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TipTopPayReceiptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receipt_aligns_amounts_when_discount_lines_are_skipped(): void
    {
        config([
            'services.tiptop_pay.receipt_vat' => null,
            'services.tiptop_pay.receipt_taxation_system' => 1,
        ]);

        $user = User::factory()->create([
            'phone' => '77011112233',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'subtotal' => 5000,
            'discount_total' => 2500,
            'total' => 2500,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Коньки',
            'quantity' => 2,
            'unit_price' => 2500,
            'amount' => 5000,
            'is_discount' => false,
            'sort_order' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Скидка будний день',
            'quantity' => 1,
            'unit_price' => -2500,
            'amount' => -2500,
            'is_discount' => true,
            'sort_order' => 2,
        ]);

        $receipt = app(TipTopPayReceiptBuilder::class)->customerReceipt($order->fresh(['items', 'user']));

        $this->assertNotNull($receipt);
        $this->assertSame(1, $receipt['TaxationSystem']);
        $this->assertSame(2500.0, $receipt['Amounts']['Electronic']);
        $this->assertCount(1, $receipt['Items']);
        $this->assertSame(2500.0, $receipt['Items'][0]['Amount']);
        $this->assertSame(1250.0, $receipt['Items'][0]['Price']);
        $this->assertSame('77011112233', $receipt['Phone']);
        $this->assertArrayNotHasKey('Email', $receipt);
        $this->assertNull($receipt['Items'][0]['Vat']);
    }

    public function test_refund_json_data_scales_to_refund_amount(): void
    {
        $order = Order::factory()->create([
            'subtotal' => 3000,
            'discount_total' => 0,
            'total' => 3000,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Латте',
            'quantity' => 2,
            'unit_price' => 1500,
            'amount' => 3000,
            'is_discount' => false,
        ]);

        $json = app(TipTopPayReceiptBuilder::class)->refundJsonData($order->fresh(['items', 'user']), 1500);

        $this->assertNotNull($json);
        $this->assertSame(1500.0, data_get($json, 'PaymentData.CustomerReceipt.Amounts.Electronic'));
        $this->assertSame(1500.0, data_get($json, 'PaymentData.CustomerReceipt.Items.0.Amount'));
    }
}
