<?php

namespace Tests\Feature;

use App\Exceptions\IikoException;
use App\Services\Iiko\IikoClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresIiko;
use Tests\TestCase;

class IikoClientTest extends TestCase
{
    use ConfiguresIiko;
    use RefreshDatabase;

    public function test_access_token_is_cached_and_reused(): void
    {
        $this->configureIiko();

        Http::fake([
            'https://api-ru.iiko.services/api/1/access_token' => Http::response([
                'token' => 'cached-token',
            ]),
            'https://api-ru.iiko.services/api/1/organizations' => Http::sequence()
                ->push(['organizations' => [['id' => 'org-1', 'name' => 'Cafe']]])
                ->push(['organizations' => [['id' => 'org-1', 'name' => 'Cafe']]]),
        ]);

        $client = app(IikoClient::class);

        $this->assertSame('cached-token', $client->accessToken());
        $this->assertSame([['id' => 'org-1', 'name' => 'Cafe']], $client->organizations());
        $this->assertSame([['id' => 'org-1', 'name' => 'Cafe']], $client->organizations());

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api-ru.iiko.services/api/1/access_token'
            && $request['apiLogin'] === 'test-api-login');
    }

    public function test_create_order_throws_on_iiko_error_status(): void
    {
        $this->configureIiko();
        $this->fakeIikoTokenAndCreateOrder(createSucceeds: false);

        $this->expectException(IikoException::class);
        $this->expectExceptionMessage('TerminalGroupIdNotDetermined');

        app(IikoClient::class)->createOrder([
            'organizationId' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'terminalGroupId' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'order' => [
                'tableIds' => ['cccccccc-cccc-cccc-cccc-cccccccccccc'],
                'items' => [],
            ],
        ]);
    }

    public function test_unconfigured_client_rejects_calls(): void
    {
        $this->configureIiko(apiLogin: null);

        $this->expectException(IikoException::class);
        $this->expectExceptionMessage('iiko не настроен');

        app(IikoClient::class)->accessToken();
    }
}
