<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;

trait ConfiguresDssSoft
{
    private function configureDssSoft(): OpenSSLAsymmetricKey
    {
        $keypair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $this->assertNotFalse($keypair);

        $details = openssl_pkey_get_details($keypair);
        $this->assertIsArray($details);

        config([
            'services.dss_soft.base_url' => 'https://api.dss-soft.kz',
            'services.dss_soft.token' => 'test-token',
            'services.dss_soft.public_key' => $details['key'],
            'services.dss_soft.public_key_path' => null,
            'services.dss_soft.show_id' => 195,
        ]);

        return $keypair;
    }

    private function fakeDssSoftCatalogAndOrder(bool $confirmSucceeds = true): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) use ($confirmSucceeds) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $request->method() === 'GET' && $path === '/partner/shows' => Http::response([
                    [
                        'id' => 195,
                        'title' => ['ru' => 'Каток Haragun'],
                        'type' => 'pricegroups',
                    ],
                ]),
                $request->method() === 'GET' && $path === '/partner/shows/195/days' => Http::response([
                    ['id' => 10, 'date' => '2026-02-13'],
                    ['id' => 11, 'date' => '2026-02-14'],
                ]),
                $request->method() === 'GET' && $path === '/partner/shows/195/days/10/slots' => Http::response([
                    [
                        'id' => 3,
                        'start' => '16:00:00',
                        'end' => '17:00:00',
                        'title' => '16:00 - 17:00',
                    ],
                ]),
                $request->method() === 'GET' && $path === '/partner/shows/195/days/10/3/prices' => Http::response([
                    ['id' => 41, 'title' => 'Взрослый (13+)', 'price' => 4000, 'amount' => 12],
                    ['id' => 42, 'title' => 'Детский', 'price' => 2000, 'amount' => 8],
                ]),
                $request->method() === 'POST' && $path === '/partner/shows/195/order' => Http::response([
                    'id' => 900,
                    'paid' => false,
                    'items' => [],
                ]),
                $request->method() === 'POST' && $path === '/partner/order/900/confirm' && $confirmSucceeds => Http::response([
                    'id' => 900,
                    'paid' => true,
                    'items' => [
                        ['pricegroup_id' => 41, 'barcode' => 'QR-ADULT-1'],
                        ['pricegroup_id' => 41, 'barcode' => 'QR-ADULT-2'],
                    ],
                ]),
                $request->method() === 'POST' && $path === '/partner/order/900/confirm' => Http::response([
                    'error' => 'Сеанс заполнен',
                ], 400),
                $request->method() === 'GET' && $path === '/partner/order/900' => Http::response([
                    'id' => 900,
                    'paid' => false,
                ]),
                $request->method() === 'DELETE' && $path === '/partner/order/900/cancel' => Http::response(['ok' => true]),
                default => Http::response(['error' => 'unexpected '.$request->method().' '.$path], 500),
            };
        });
    }
}
