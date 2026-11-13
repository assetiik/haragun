<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TipTopPay3dsValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_pa_res_from_test_acs_is_accepted_and_forwarded(): void
    {
        config([
            'services.tiptop_pay.enabled' => true,
            'services.tiptop_pay.base_url' => 'https://api.tiptoppay.test',
            'services.tiptop_pay.public_id' => 'pk_test_public',
            'services.tiptop_pay.api_secret' => 'test_api_secret',
        ]);

        $user = User::factory()->create();
        $coffee = Establishment::factory()->create([
            'type' => EstablishmentType::Coffee,
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $coffee->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Accepted,
            'total' => 10,
            'payment_status' => PaymentStatus::Pending,
            'payment_provider' => 'tiptop',
            'tiptop_transaction_id' => 4747847641,
            'paid_at' => null,
            'accepted_at' => null,
        ]);

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            if (str_ends_with($path, '/payments/cards/post3ds')) {
                $this->assertSame(4747847641, (int) $request['TransactionId']);
                $this->assertSame('AQ==', $request['PaRes']);

                return Http::response([
                    'Success' => true,
                    'Message' => null,
                    'Model' => [
                        'TransactionId' => 4747847641,
                        'Amount' => 10,
                        'CardType' => 'Visa',
                        'CardLastFour' => '4242',
                        'Status' => 'Completed',
                    ],
                ]);
            }

            return Http::response(['ok' => true]);
        });

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/payments/3ds", [
            'transaction_id' => 4747847641,
            'pa_res' => 'AQ==',
        ])
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('payment.required', false);
    }
}
