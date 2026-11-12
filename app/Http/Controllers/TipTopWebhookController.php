<?php

namespace App\Http\Controllers;

use App\Services\TipTopPay\TipTopPayClient;
use App\Services\TipTopPay\TipTopPayService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

#[Group('Payments', weight: 9)]
class TipTopWebhookController extends Controller
{
    public function __construct(
        private TipTopPayService $payments,
        private TipTopPayClient $client,
    ) {}

    public function check(Request $request): JsonResponse
    {
        return $this->handle($request, fn (array $payload): array => $this->payments->handleCheck($payload));
    }

    public function pay(Request $request): JsonResponse
    {
        return $this->handle($request, fn (array $payload): array => $this->payments->handlePay($payload));
    }

    public function fail(Request $request): JsonResponse
    {
        return $this->handle($request, fn (array $payload): array => $this->payments->handleFail($payload));
    }

    public function refund(Request $request): JsonResponse
    {
        return $this->handle($request, fn (array $payload): array => $this->payments->handleRefund($payload));
    }

    /**
     * @param  callable(array<string, mixed>): array{code: int}  $callback
     */
    private function handle(Request $request, callable $callback): JsonResponse
    {
        $rawBody = $request->getContent();
        $hmac = $request->header('Content-HMAC') ?? $request->header('X-Content-HMAC');

        if (! $this->client->verifyNotificationHmac($rawBody, is_string($hmac) ? $hmac : null)) {
            Log::warning('TipTop Pay webhook: неверная HMAC-подпись', [
                'path' => $request->path(),
            ]);

            return response()->json(['code' => 13]);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return response()->json($callback($payload));
    }
}
