<?php

namespace Tests\Feature;

use App\Exceptions\RinkApiException;
use App\Services\RinkApi\RinkApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresRinkApi;
use Tests\TestCase;

class RinkApiClientTest extends TestCase
{
    use ConfiguresRinkApi;

    public function test_sessions_sends_api_key_and_returns_payload(): void
    {
        $this->configureRinkApi();

        Http::preventStrayRequests();
        Http::fake([
            'rink.test/api/v1/sessions*' => Http::response([
                'date' => '2026-02-13',
                'sessions' => [],
            ]),
        ]);

        $payload = app(RinkApiClient::class)->sessions('2026-02-13');

        $this->assertSame('2026-02-13', $payload['date']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/sessions')
            && $request->hasHeader('X-API-Key', 'rk_test_key')
            && $request['date'] === '2026-02-13');
    }

    public function test_error_response_maps_message_ru_and_code(): void
    {
        $this->configureRinkApi();

        Http::preventStrayRequests();
        Http::fake([
            'rink.test/api/v1/orders' => Http::response([
                'error' => [
                    'code' => 'sold_out',
                    'message' => 'Not enough places.',
                    'message_ru' => 'Мест нет.',
                    'details' => ['remaining' => 0],
                ],
            ], 409),
        ]);

        try {
            app(RinkApiClient::class)->createOrder([
                'session_id' => 41,
                'external_order_id' => 'APP-1',
                'sale_kind' => 'online',
                'items' => [['ticket_type' => 'adult', 'quantity' => 1, 'unit_price' => 4000]],
            ]);
            $this->fail('Expected RinkApiException');
        } catch (RinkApiException $exception) {
            $this->assertSame('Мест нет.', $exception->getMessage());
            $this->assertSame('sold_out', $exception->errorCode);
            $this->assertSame(422, $exception->status);
            $this->assertSame(['remaining' => 0], $exception->details);
        }
    }

    public function test_is_configured_requires_key(): void
    {
        config([
            'services.rink_api.base_url' => 'https://rink.test/api/v1',
            'services.rink_api.api_key' => null,
        ]);

        $this->assertFalse(app(RinkApiClient::class)->isConfigured());
    }
}
