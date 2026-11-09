<?php

namespace Tests\Feature;

use App\Exceptions\MobizonException;
use App\Services\Mobizon\MobizonClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobizonClientTest extends TestCase
{
    public function test_send_sms_posts_form_payload_to_mobizon(): void
    {
        config([
            'services.mobizon.api_key' => 'test-mobizon-key',
            'services.mobizon.base_url' => 'https://api.mobizon.kz',
            'services.mobizon.from' => 'Haragun',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobizon.kz/service/message/sendSmsMessage*' => Http::response([
                'code' => 0,
                'data' => [
                    'campaignId' => 10,
                    'messageId' => 20,
                    'status' => 2,
                ],
                'message' => '',
            ]),
        ]);

        app(MobizonClient::class)->sendSms('77784046889', 'Ваш код для входа в Haragun: 4321');

        Http::assertSent(function (Request $request): bool {
            parse_str((string) $request->body(), $body);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), 'https://api.mobizon.kz/service/message/sendSmsMessage')
                && ($query['apiKey'] ?? null) === 'test-mobizon-key'
                && ($query['output'] ?? null) === 'json'
                && ($body['recipient'] ?? null) === '77784046889'
                && ($body['text'] ?? null) === 'Ваш код для входа в Haragun: 4321'
                && ($body['from'] ?? null) === 'Haragun';
        });
    }

    public function test_send_sms_throws_when_mobizon_returns_error_code(): void
    {
        config([
            'services.mobizon.api_key' => 'test-mobizon-key',
            'services.mobizon.base_url' => 'https://api.mobizon.kz',
            'services.mobizon.from' => 'Haragun',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.mobizon.kz/service/message/sendSmsMessage*' => Http::response([
                'code' => 1,
                'data' => null,
                'message' => 'Incorrect parameters',
            ]),
        ]);

        try {
            app(MobizonClient::class)->sendSms('77784046889', 'code');
            $this->fail('Expected MobizonException');
        } catch (MobizonException $exception) {
            $this->assertSame(502, $exception->status);
            $this->assertSame('Incorrect parameters', $exception->getMessage());
        }
    }

    public function test_send_sms_requires_api_key(): void
    {
        config(['services.mobizon.api_key' => null]);

        $this->expectException(MobizonException::class);

        app(MobizonClient::class)->sendSms('77784046889', 'code');
    }
}
