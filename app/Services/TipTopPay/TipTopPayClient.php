<?php

namespace App\Services\TipTopPay;

use App\Exceptions\TipTopPayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class TipTopPayClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.tiptop_pay.public_id'))
            && filled(config('services.tiptop_pay.api_secret'));
    }

    public function publicId(): string
    {
        return (string) config('services.tiptop_pay.public_id');
    }

    public function apiSecret(): string
    {
        return (string) config('services.tiptop_pay.api_secret');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function chargeCard(array $payload): array
    {
        return $this->jsonObject($this->send('POST', '/payments/cards/charge', $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post3ds(array $payload): array
    {
        return $this->jsonObject($this->send('POST', '/payments/cards/post3ds', $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refund(array $payload): array
    {
        return $this->jsonObject($this->send('POST', '/payments/refund', $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function getPayment(array $payload): array
    {
        return $this->jsonObject($this->send('POST', '/payments/get', $payload));
    }

    public function verifyNotificationHmac(string $rawBody, ?string $contentHmac): bool
    {
        if (! filled($contentHmac) || ! $this->isConfigured()) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $this->apiSecret(), true));

        return hash_equals($expected, $contentHmac);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload = []): Response
    {
        if (! $this->isConfigured()) {
            throw new TipTopPayException('TipTop Pay не настроен.', 503, 'tiptop_not_configured', path: $path);
        }

        try {
            $response = $this->http()->send($method, $path, [
                'json' => $payload,
            ]);
        } catch (ConnectionException $exception) {
            throw new TipTopPayException(
                'TipTop Pay временно недоступен.',
                502,
                'tiptop_unreachable',
                ['error' => $exception->getMessage()],
                $path,
            );
        } catch (Throwable $exception) {
            throw new TipTopPayException(
                'Ошибка запроса к TipTop Pay.',
                502,
                'tiptop_request_failed',
                ['error' => $exception->getMessage()],
                $path,
            );
        }

        if ($response->status() === 401) {
            throw new TipTopPayException('Неверные ключи TipTop Pay.', 502, 'tiptop_unauthorized', path: $path);
        }

        if ($response->status() === 429) {
            throw new TipTopPayException('Слишком много запросов к TipTop Pay.', 429, 'tiptop_rate_limited', path: $path);
        }

        if (! $response->successful() && ! $response->json()) {
            throw new TipTopPayException(
                'TipTop Pay вернул ошибку.',
                502,
                'tiptop_http_error',
                ['status' => $response->status(), 'body' => $response->body()],
                $path,
            );
        }

        return $response;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.tiptop_pay.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($this->publicId(), $this->apiSecret())
            ->connectTimeout((int) config('services.tiptop_pay.connect_timeout', 3))
            ->timeout((int) config('services.tiptop_pay.timeout', 30));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            throw new TipTopPayException(
                'Некорректный ответ TipTop Pay.',
                502,
                'tiptop_invalid_response',
                ['body' => $response->body()],
            );
        }

        return $json;
    }
}
