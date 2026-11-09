<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait ConfiguresIiko
{
    protected function configureIiko(?string $apiLogin = 'test-api-login'): void
    {
        config([
            'services.iiko.base_url' => 'https://api-ru.iiko.services',
            'services.iiko.api_login' => $apiLogin,
            'services.iiko.token_cache_key' => 'iiko.access_token.test',
            'services.iiko.token_ttl_seconds' => 3300,
            'services.iiko.command_poll_attempts' => 3,
            'services.iiko.command_poll_ms' => 0,
        ]);

        Cache::forget('iiko.access_token.test');
    }

    protected function fakeIikoTokenAndCreateOrder(
        string $orderId = '11111111-1111-1111-1111-111111111111',
        string $correlationId = '22222222-2222-2222-2222-222222222222',
        bool $createSucceeds = true,
    ): void {
        Http::fake([
            'https://api-ru.iiko.services/api/1/access_token' => Http::response([
                'token' => 'test-iiko-token',
            ]),
            'https://api-ru.iiko.services/api/1/order/create' => $createSucceeds
                ? Http::response([
                    'correlationId' => $correlationId,
                    'orderInfo' => [
                        'id' => $orderId,
                        'creationStatus' => 'Success',
                        'organizationId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
                    ],
                ])
                : Http::response([
                    'correlationId' => $correlationId,
                    'orderInfo' => [
                        'id' => '00000000-0000-0000-0000-000000000000',
                        'creationStatus' => 'Error',
                        'errorInfo' => [
                            'message' => 'TerminalGroupIdNotDetermined',
                            'description' => 'Терминальная группа не определена.',
                        ],
                    ],
                ], 200),
            'https://api-ru.iiko.services/api/1/commands/status' => Http::response([
                'state' => 'Success',
            ]),
        ]);
    }
}
