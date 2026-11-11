<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

trait ConfiguresRinkApi
{
    private function configureRinkApi(): void
    {
        config([
            'services.ticket_provider' => 'rink_api',
            'services.rink_api.base_url' => 'https://rink.test/api/v1',
            'services.rink_api.api_key' => 'rk_test_key',
            'services.rink_api.sale_kind' => 'online',
            'services.dss_soft.token' => null,
        ]);
    }

    private function fakeRinkApiCatalogAndOrder(bool $confirmSucceeds = true): void
    {
        Http::preventStrayRequests();

        Http::fake(function (Request $request) use ($confirmSucceeds) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $request->method() === 'GET' && $path === '/api/v1/sessions' => Http::response([
                    'date' => '2026-02-13',
                    'day_type' => 'weekday',
                    'sessions' => [
                        [
                            'id' => 41,
                            'name' => ['ru' => 'Сеанс 1', 'en' => 'Session 1'],
                            'starts_at' => '2026-02-13T16:00:00+05:00',
                            'ends_at' => '2026-02-13T17:00:00+05:00',
                            'entry_opens_at' => '2026-02-13T15:00:00+05:00',
                            'entry_closes_at' => '2026-02-13T17:00:00+05:00',
                            'status' => 'open',
                            'capacity' => 400,
                            'remaining' => 372,
                            'sale_state' => 'on_sale',
                            'on_sale' => true,
                            'prices' => [
                                [
                                    'ticket_type' => 'adult',
                                    'name' => ['ru' => 'Взрослый', 'en' => 'Adult'],
                                    'price' => 4000,
                                ],
                                [
                                    'ticket_type' => 'child',
                                    'name' => ['ru' => 'Детский', 'en' => 'Child'],
                                    'price' => 2000,
                                ],
                            ],
                        ],
                    ],
                ]),
                $request->method() === 'POST' && $path === '/api/v1/orders' => Http::response([
                    'order_id' => 'ord_test_hold_1',
                    'status' => 'held',
                    'total' => 8000,
                    'hold_expires_at' => '2026-02-13T12:10:00+05:00',
                    'tickets' => [],
                ], 201),
                $request->method() === 'POST' && $path === '/api/v1/orders/ord_test_hold_1/confirm' && $confirmSucceeds => Http::response([
                    'order_id' => 'ord_test_hold_1',
                    'status' => 'confirmed',
                    'tickets' => [
                        ['code' => 'ZYULLQB3QR5ZWZLN5LTT', 'ticket_type' => 'adult', 'price' => 4000, 'status' => 'active', 'session_id' => 41],
                        ['code' => 'BUH4853QM27CP3455D9A', 'ticket_type' => 'adult', 'price' => 4000, 'status' => 'active', 'session_id' => 41],
                    ],
                ]),
                $request->method() === 'POST' && $path === '/api/v1/orders/ord_test_hold_1/confirm' => Http::response([
                    'error' => [
                        'code' => 'sold_out',
                        'message' => 'Not enough places left in this session.',
                        'message_ru' => 'В этом сеансе недостаточно свободных мест.',
                        'details' => ['session_id' => 41, 'remaining' => 0],
                    ],
                ], 409),
                $request->method() === 'POST' && $path === '/api/v1/orders/ord_test_hold_1/release' => Http::response([
                    'order_id' => 'ord_test_hold_1',
                    'status' => 'released',
                ]),
                default => Http::response(['error' => ['code' => 'not_found', 'message' => 'unexpected '.$request->method().' '.$path]], 404),
            };
        });
    }
}
