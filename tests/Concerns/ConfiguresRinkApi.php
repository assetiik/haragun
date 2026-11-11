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
            $date = (string) ($request['date'] ?? '2026-02-13');

            return match (true) {
                $request->method() === 'GET' && $path === '/api/v1/sessions' => Http::response([
                    'date' => $date,
                    'day_type' => 'weekday',
                    'sessions' => [
                        [
                            'id' => 41,
                            'name' => ['ru' => 'Сеанс 1', 'en' => 'Session 1'],
                            'starts_at' => $date.'T16:00:00+05:00',
                            'ends_at' => $date.'T17:00:00+05:00',
                            'entry_opens_at' => $date.'T15:00:00+05:00',
                            'entry_closes_at' => $date.'T17:00:00+05:00',
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
                        [
                            'id' => 57,
                            'name' => ['ru' => 'Сеанс 2', 'en' => 'Session 2'],
                            'starts_at' => $date.'T19:00:00+05:00',
                            'ends_at' => $date.'T21:00:00+05:00',
                            'entry_opens_at' => $date.'T18:00:00+05:00',
                            'entry_closes_at' => $date.'T21:00:00+05:00',
                            'status' => 'open',
                            'capacity' => 400,
                            'remaining' => 300,
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
                $request->method() === 'GET' && str_starts_with($path, '/api/v1/promo-codes/') => Http::response([
                    'code' => 'TEST10',
                    'name' => 'Test 10%',
                    'kind' => 'percent',
                    'value' => 10,
                    'ticket_types' => null,
                    'remaining' => 100,
                    'session_id' => (int) ($request['session_id'] ?? 41),
                    'prices' => [
                        [
                            'ticket_type' => 'child',
                            'name' => ['ru' => 'Детский', 'en' => 'Child'],
                            'full_price' => 2000,
                            'price' => 1800,
                            'discounted' => true,
                        ],
                        [
                            'ticket_type' => 'adult',
                            'name' => ['ru' => 'Взрослый', 'en' => 'Adult'],
                            'full_price' => 4000,
                            'price' => 3600,
                            'discounted' => true,
                        ],
                    ],
                ]),
                $request->method() === 'POST' && $path === '/api/v1/orders' => Http::response([
                    'order_id' => 'ord_test_hold_1',
                    'status' => 'held',
                    'total' => (int) ($request['expected_total'] ?? 8000),
                    'promo_code' => $request['promo_code'] ?? null,
                    'discount' => isset($request['promo_code']) ? 400 : 0,
                    'hold_expires_at' => '2026-02-13T12:10:00+05:00',
                    'tickets' => [],
                ], 201),
                $request->method() === 'POST' && $path === '/api/v1/orders/ord_test_hold_1/confirm' && $confirmSucceeds => Http::response([
                    'order_id' => 'ord_test_hold_1',
                    'status' => 'confirmed',
                    'tickets' => [
                        ['code' => 'ZYULLQB3QR5ZWZLN5LTT', 'ticket_type' => 'adult', 'price' => 3600, 'full_price' => 4000, 'status' => 'active', 'session_id' => 41, 'can_refund' => true, 'can_move' => true],
                        ['code' => 'BUH4853QM27CP3455D9A', 'ticket_type' => 'adult', 'price' => 3600, 'full_price' => 4000, 'status' => 'active', 'session_id' => 41, 'can_refund' => true, 'can_move' => true],
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
                $request->method() === 'GET' && $path === '/api/v1/tickets/ZYULLQB3QR5ZWZLN5LTT' => Http::response([
                    'code' => 'ZYULLQB3QR5ZWZLN5LTT',
                    'ticket_type' => 'adult',
                    'price' => 4000,
                    'full_price' => 4000,
                    'status' => 'active',
                    'can_refund' => true,
                    'can_move' => true,
                    'session_id' => 41,
                    'session' => [
                        'status' => 'open',
                        'starts_at' => '2026-02-13T16:00:00+05:00',
                        'ends_at' => '2026-02-13T17:00:00+05:00',
                        'entry_opens_at' => '2026-02-13T15:00:00+05:00',
                    ],
                ]),
                $request->method() === 'GET' && $path === '/api/v1/tickets/BUH4853QM27CP3455D9A' => Http::response([
                    'code' => 'BUH4853QM27CP3455D9A',
                    'ticket_type' => 'adult',
                    'price' => 4000,
                    'full_price' => 4000,
                    'status' => 'active',
                    'can_refund' => true,
                    'can_move' => true,
                    'session_id' => 41,
                    'session' => [
                        'status' => 'open',
                        'starts_at' => '2026-02-13T16:00:00+05:00',
                        'ends_at' => '2026-02-13T17:00:00+05:00',
                        'entry_opens_at' => '2026-02-13T15:00:00+05:00',
                    ],
                ]),
                $request->method() === 'POST' && $path === '/api/v1/tickets/ZYULLQB3QR5ZWZLN5LTT/refund' => Http::response([
                    'code' => 'ZYULLQB3QR5ZWZLN5LTT',
                    'ticket_type' => 'adult',
                    'price' => 4000,
                    'status' => 'refunded',
                    'can_refund' => false,
                    'can_move' => false,
                    'session_id' => 41,
                ]),
                $request->method() === 'POST' && $path === '/api/v1/tickets/ZYULLQB3QR5ZWZLN5LTT/move' => Http::response([
                    'code' => 'ZYULLQB3QR5ZWZLN5LTT',
                    'ticket_type' => 'adult',
                    'price' => 4000,
                    'status' => 'active',
                    'can_refund' => true,
                    'can_move' => true,
                    'session_id' => (int) ($request['session_id'] ?? 57),
                    'session' => [
                        'status' => 'open',
                        'starts_at' => '2026-02-13T19:00:00+05:00',
                        'ends_at' => '2026-02-13T21:00:00+05:00',
                        'entry_opens_at' => '2026-02-13T18:00:00+05:00',
                    ],
                ]),
                default => Http::response(['error' => ['code' => 'not_found', 'message' => 'unexpected '.$request->method().' '.$path]], 404),
            };
        });
    }
}
