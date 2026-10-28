<?php

namespace Tests\Feature;

use App\Exceptions\DssSoftException;
use App\Services\DssSoft\DssSoftClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ConfiguresDssSoft;
use Tests\TestCase;

class DssSoftClientTest extends TestCase
{
    use ConfiguresDssSoft;

    public function test_shows_request_includes_signed_partner_headers(): void
    {
        $privateKey = $this->configureDssSoft();

        Http::preventStrayRequests();
        Http::fake([
            'https://api.dss-soft.kz/partner/shows' => Http::response([
                ['id' => 195, 'title' => ['ru' => 'Каток Haragun']],
            ]),
        ]);

        $shows = app(DssSoftClient::class)->shows();

        $this->assertSame(195, $shows[0]['id']);

        Http::assertSent(function (Request $request) use ($privateKey): bool {
            if ($request->url() !== 'https://api.dss-soft.kz/partner/shows') {
                return false;
            }

            $timestamp = $request->header('TIMESTAMP')[0] ?? '';
            $signature = $request->header('SIGNATURE')[0] ?? '';
            $plain = '';
            $decoded = base64_decode($signature, true);

            $this->assertNotFalse($decoded);
            $this->assertTrue(openssl_private_decrypt($decoded, $plain, $privateKey, OPENSSL_PKCS1_OAEP_PADDING));
            $this->assertSame('/partner/shows'.$timestamp, $plain);

            return ($request->header('X-PARTNER-TOKEN')[0] ?? '') === 'test-token'
                && ctype_digit($timestamp);
        });
    }

    public function test_unauthorized_catalog_request_returns_502(): void
    {
        $this->configureDssSoft();

        Http::preventStrayRequests();
        Http::fake([
            'https://api.dss-soft.kz/partner/shows' => Http::response([
                'success' => false,
                'code' => 401,
                'message' => 'Авторизация не успешна',
            ], 401),
        ]);

        try {
            app(DssSoftClient::class)->shows();
            $this->fail('Expected DssSoftException');
        } catch (DssSoftException $exception) {
            $this->assertSame(502, $exception->status);
            $this->assertSame('Авторизация не успешна', $exception->getMessage());
        }
    }

    public function test_one_line_pem_can_sign_requests(): void
    {
        $this->configureDssSoft();
        $wrapped = (string) config('services.dss_soft.public_key');
        config(['services.dss_soft.public_key' => str_replace(["\n", "\r"], '', $wrapped)]);

        Http::preventStrayRequests();
        Http::fake([
            'https://api.dss-soft.kz/partner/shows' => Http::response([
                ['id' => 195],
            ]),
        ]);

        $this->assertSame(195, app(DssSoftClient::class)->shows()[0]['id']);
    }

    public function test_client_is_disabled_without_token(): void
    {
        config([
            'services.dss_soft.token' => null,
            'services.dss_soft.public_key' => '-----BEGIN PUBLIC KEY-----',
            'services.dss_soft.show_id' => 195,
        ]);

        $this->assertFalse(app(DssSoftClient::class)->isConfigured());
    }
}
