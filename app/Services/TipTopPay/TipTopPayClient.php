<?php

namespace App\Services\TipTopPay;

use App\Enums\EstablishmentType;
use App\Exceptions\TipTopPayException;
use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class TipTopPayClient
{
    public const MERCHANT_ICE = 'ice';

    public const MERCHANT_COFFEE = 'coffee';

    public function merchantForOrder(Order $order): ?string
    {
        $order->loadMissing('establishment');

        return match ($order->establishment?->type) {
            EstablishmentType::IceArena => self::MERCHANT_ICE,
            EstablishmentType::Coffee => self::MERCHANT_COFFEE,
            default => null,
        };
    }

    public function merchantForType(?EstablishmentType $type): ?string
    {
        return match ($type) {
            EstablishmentType::IceArena => self::MERCHANT_ICE,
            EstablishmentType::Coffee => self::MERCHANT_COFFEE,
            default => null,
        };
    }

    public function isConfigured(?string $merchant = null): bool
    {
        if ($merchant === null) {
            return $this->isConfigured(self::MERCHANT_ICE)
                || $this->isConfigured(self::MERCHANT_COFFEE);
        }

        return filled($this->publicId($merchant)) && filled($this->apiSecret($merchant));
    }

    public function publicId(string $merchant): string
    {
        return (string) config("services.tiptop_pay.merchants.{$merchant}.public_id");
    }

    public function apiSecret(string $merchant): string
    {
        return (string) config("services.tiptop_pay.merchants.{$merchant}.api_secret");
    }

    /**
     * @return list<string>
     */
    public function configuredSecrets(): array
    {
        $secrets = [];

        foreach ([self::MERCHANT_ICE, self::MERCHANT_COFFEE] as $merchant) {
            if ($this->isConfigured($merchant)) {
                $secrets[] = $this->apiSecret($merchant);
            }
        }

        return array_values(array_unique($secrets));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function chargeCard(array $payload, string $merchant): array
    {
        return $this->jsonObject($this->send('POST', '/payments/cards/charge', $payload, $merchant));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post3ds(array $payload, string $merchant): array
    {
        return $this->jsonObject($this->send('POST', '/payments/cards/post3ds', $payload, $merchant));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function refund(array $payload, string $merchant): array
    {
        return $this->jsonObject($this->send('POST', '/payments/refund', $payload, $merchant));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function getPayment(array $payload, string $merchant): array
    {
        return $this->jsonObject($this->send('POST', '/payments/get', $payload, $merchant));
    }

    public function verifyNotificationHmac(string $rawBody, ?string $contentHmac): bool
    {
        if (! filled($contentHmac)) {
            return false;
        }

        foreach ($this->configuredSecrets() as $secret) {
            $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

            if (hash_equals($expected, $contentHmac)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $path, array $payload, string $merchant): Response
    {
        if (! $this->isConfigured($merchant)) {
            throw new TipTopPayException('TipTop Pay не настроен.', 503, 'tiptop_not_configured', path: $path);
        }

        try {
            $response = $this->http($merchant)->send($method, $path, [
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

    private function http(string $merchant): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.tiptop_pay.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->withBasicAuth($this->publicId($merchant), $this->apiSecret($merchant))
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
