<?php

namespace App\Services\RinkApi;

use App\Exceptions\RinkApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class RinkApiClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.rink_api.base_url'))
            && filled(config('services.rink_api.api_key'));
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        $response = Http::baseUrl($this->baseUrl())
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->acceptJson()
            ->get('/health');

        if (! $response->successful()) {
            throw new RinkApiException('Билетная система временно недоступна.', 502, 'server_error', [], '/health');
        }

        return $this->jsonObject($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function sessions(string $date): array
    {
        return $this->jsonObject($this->send('GET', '/sessions', ['date' => $date]));
    }

    /**
     * @return array<string, mixed>
     */
    public function session(int $sessionId): array
    {
        return $this->jsonObject($this->send('GET', "/sessions/{$sessionId}"));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ticketTypes(): array
    {
        $json = $this->jsonObject($this->send('GET', '/ticket-types'));

        $types = $json['ticket_types'] ?? $json['data'] ?? $json;

        return is_array($types) && array_is_list($types) ? $types : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload): array
    {
        return $this->jsonObject($this->send('POST', '/orders', $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function order(string $orderId): array
    {
        return $this->jsonObject($this->send('GET', "/orders/{$orderId}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmOrder(string $orderId): array
    {
        return $this->jsonObject($this->send('POST', "/orders/{$orderId}/confirm"));
    }

    public function releaseOrder(string $orderId): void
    {
        $this->send('POST', "/orders/{$orderId}/release");
    }

    /**
     * @return array<string, mixed>
     */
    public function ticket(string $code): array
    {
        return $this->jsonObject($this->send('GET', "/tickets/{$code}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function promoCode(string $code, int $sessionId): array
    {
        return $this->jsonObject($this->send('GET', '/promo-codes/'.rawurlencode($code), [
            'session_id' => $sessionId,
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refundTicket(string $code, array $payload = []): array
    {
        return $this->jsonObject($this->send('POST', "/tickets/{$code}/refund", $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function moveTicket(string $code, array $payload): array
    {
        return $this->jsonObject($this->send('POST', "/tickets/{$code}/move", $payload));
    }

    /**
     * @param  array<string, mixed>  $queryOrBody
     */
    private function send(string $method, string $path, array $queryOrBody = []): Response
    {
        if (! $this->isConfigured()) {
            throw new RinkApiException('Rink API не настроен: нет base URL или ключа.');
        }

        $path = '/'.ltrim($path, '/');
        $request = Http::baseUrl($this->baseUrl())
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-API-Key' => (string) config('services.rink_api.api_key'),
            ]);

        if ($method === 'GET') {
            $request = $request->retry(
                [100, 500],
                0,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                false,
            );
        }

        $response = match ($method) {
            'GET' => $request->get($path, $queryOrBody),
            'POST' => $request->post($path, $queryOrBody),
            default => throw new RinkApiException("Неподдерживаемый метод Rink API: {$method}"),
        };

        if (! $response->successful()) {
            $this->throwFrom($response, $path);
        }

        return $response;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.rink_api.base_url'), '/');
    }

    private function connectTimeout(): int
    {
        return (int) config('services.rink_api.connect_timeout', 3);
    }

    private function timeout(): int
    {
        return (int) config('services.rink_api.timeout', 15);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function throwFrom(Response $response, string $path): never
    {
        $error = $response->json('error');
        $code = is_array($error) ? ($error['code'] ?? null) : null;
        $message = is_array($error)
            ? ($error['message_ru'] ?? $error['message'] ?? null)
            : null;
        $details = is_array($error) && is_array($error['details'] ?? null)
            ? $error['details']
            : [];

        $message = is_string($message) && $message !== ''
            ? $message
            : 'Билетная система временно недоступна';

        $status = match (true) {
            $response->status() === 401 => 401,
            in_array($response->status(), [404, 409, 422], true) => 422,
            default => 502,
        };

        throw new RinkApiException(
            $message,
            $status,
            is_string($code) ? $code : null,
            $details,
            $path,
        );
    }
}
