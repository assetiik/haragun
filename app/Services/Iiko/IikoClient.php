<?php

namespace App\Services\Iiko;

use App\Exceptions\IikoException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class IikoClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.iiko.api_login'));
    }

    public function accessToken(bool $forceRefresh = false): string
    {
        if (! $this->isConfigured()) {
            throw new IikoException('iiko не настроен: нет apiLogin.');
        }

        $cacheKey = (string) config('services.iiko.token_cache_key');

        if (! $forceRefresh) {
            $cached = Cache::get($cacheKey);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $response = $this->baseRequest()
            ->post('/api/1/access_token', [
                'apiLogin' => (string) config('services.iiko.api_login'),
            ]);

        if (! $response->successful()) {
            $this->throwFrom($response, '/api/1/access_token');
        }

        $token = (string) ($response->json('token') ?? $response->json('access_token') ?? '');

        if ($token === '') {
            throw new IikoException('iiko не вернул access token.', 502, '/api/1/access_token');
        }

        Cache::put(
            $cacheKey,
            $token,
            now()->addSeconds((int) config('services.iiko.token_ttl_seconds', 3300)),
        );

        return $token;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function organizations(): array
    {
        $response = $this->authorized()->post('/api/1/organizations', [
            'returnAdditionalInfo' => false,
            'includeDisabled' => false,
        ]);

        if (! $response->successful()) {
            $this->throwFrom($response, '/api/1/organizations');
        }

        $organizations = $response->json('organizations');

        return is_array($organizations) ? array_values($organizations) : [];
    }

    /**
     * @param  list<string>  $organizationIds
     * @return list<array<string, mixed>>
     */
    public function terminalGroups(array $organizationIds): array
    {
        $response = $this->authorized()->post('/api/1/terminal_groups', [
            'organizationIds' => array_values($organizationIds),
        ]);

        if (! $response->successful()) {
            $this->throwFrom($response, '/api/1/terminal_groups');
        }

        $groups = $response->json('terminalGroups');

        return is_array($groups) ? array_values($groups) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function nomenclature(string $organizationId): array
    {
        $response = $this->authorized()->post('/api/1/nomenclature', [
            'organizationId' => $organizationId,
        ]);

        if (! $response->successful()) {
            $this->throwFrom($response, '/api/1/nomenclature');
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  list<string>  $organizationIds
     * @return list<array<string, mixed>>
     */
    public function paymentTypes(array $organizationIds): array
    {
        $response = $this->authorized()->post('/api/1/payment_types', [
            'organizationIds' => array_values($organizationIds),
        ]);

        if (! $response->successful()) {
            $this->throwFrom($response, '/api/1/payment_types');
        }

        $types = $response->json('paymentTypes');

        return is_array($types) ? array_values($types) : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        $response = $this->authorized()->post('/api/1/order/create', $payload);

        if (! $response->successful()) {
            $this->throwFrom($response, '/api/1/order/create');
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new IikoException('Пустой ответ iiko при создании заказа.', 502, '/api/1/order/create');
        }

        $status = (string) data_get($json, 'orderInfo.creationStatus', '');

        if (strcasecmp($status, 'Error') === 0) {
            $message = (string) (
                data_get($json, 'orderInfo.errorInfo.message')
                ?? data_get($json, 'orderInfo.errorInfo.description')
                ?? 'Не удалось создать заказ в iiko.'
            );

            throw new IikoException($message, 422, '/api/1/order/create');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function commandStatus(string $organizationId, string $correlationId): array
    {
        $response = $this->authorized()->post('/api/1/commands/status', [
            'organizationId' => $organizationId,
            'correlationId' => $correlationId,
        ]);

        if (! $response->successful()) {
            $this->throwFrom($response, '/api/1/commands/status');
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Wait until an async iiko command finishes (Success / Error).
     *
     * @return array<string, mixed>
     */
    public function waitForCommand(string $organizationId, string $correlationId, ?int $attempts = null): array
    {
        $attempts ??= (int) config('services.iiko.command_poll_attempts', 10);
        $sleepMs = (int) config('services.iiko.command_poll_ms', 400);
        $last = [];

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $last = $this->commandStatus($organizationId, $correlationId);
            $state = (string) ($last['state'] ?? '');

            if (strcasecmp($state, 'Success') === 0) {
                return $last;
            }

            if (strcasecmp($state, 'Error') === 0) {
                $message = (string) (
                    data_get($last, 'errorInfo.message')
                    ?? data_get($last, 'error.description')
                    ?? 'Команда iiko завершилась ошибкой.'
                );

                throw new IikoException($message, 422, '/api/1/commands/status');
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        throw new IikoException('Таймаут ожидания ответа iiko.', 502, '/api/1/commands/status');
    }

    public function forgetToken(): void
    {
        Cache::forget((string) config('services.iiko.token_cache_key'));
    }

    private function authorized(): PendingRequest
    {
        return $this->baseRequest()
            ->withToken($this->accessToken())
            ->retry(
                1,
                0,
                function (Throwable $exception, PendingRequest $request): bool {
                    if (! $exception instanceof RequestException) {
                        return false;
                    }

                    if ($exception->response->status() !== 401) {
                        return false;
                    }

                    $this->forgetToken();
                    $request->withToken($this->accessToken(forceRefresh: true));

                    return true;
                },
                throw: false,
            );
    }

    private function baseRequest(): PendingRequest
    {
        return Http::baseUrl((string) config('services.iiko.base_url'))
            ->connectTimeout((int) config('services.iiko.connect_timeout', 3))
            ->timeout((int) config('services.iiko.timeout', 15))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Timeout' => (string) config('services.iiko.timeout', 15),
            ])
            ->retry(
                [100, 500],
                0,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                false,
            );
    }

    private function throwFrom(Response $response, string $path): never
    {
        $message = $response->json('errorDescription')
            ?? $response->json('message')
            ?? $response->json('error')
            ?? 'Касса iiko временно недоступна';

        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE) ?: 'Касса iiko временно недоступна';
        }

        $status = in_array($response->status(), [400, 401, 403, 404, 422], true) ? 422 : 502;

        throw new IikoException(Str::limit((string) $message, 500), $status, $path);
    }
}
