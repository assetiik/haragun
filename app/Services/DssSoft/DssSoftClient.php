<?php

namespace App\Services\DssSoft;

use App\Exceptions\DssSoftException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use Throwable;

class DssSoftClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.dss_soft.token'))
            && (int) config('services.dss_soft.show_id') > 0
            && filled($this->publicKeyPem());
    }

    public function showId(): int
    {
        return (int) config('services.dss_soft.show_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function shows(): array
    {
        return $this->jsonList($this->send('GET', '/partner/shows'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function days(?int $showId = null): array
    {
        $showId ??= $this->showId();

        return $this->jsonList($this->send('GET', "/partner/shows/{$showId}/days"));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function slots(int $dayId, ?int $showId = null): array
    {
        $showId ??= $this->showId();

        return $this->jsonList($this->send('GET', "/partner/shows/{$showId}/days/{$dayId}/slots"));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function prices(int $dayId, int $slotId, ?int $showId = null): array
    {
        $showId ??= $this->showId();

        return $this->jsonList($this->send('GET', "/partner/shows/{$showId}/days/{$dayId}/{$slotId}/prices"));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createOrder(array $payload, ?int $showId = null): array
    {
        $showId ??= $this->showId();

        return $this->jsonObject($this->send('POST', "/partner/shows/{$showId}/order", $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function order(int $orderId): array
    {
        return $this->jsonObject($this->send('GET', "/partner/order/{$orderId}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmOrder(int $orderId): array
    {
        $response = $this->send('POST', "/partner/order/{$orderId}/confirm", [], false);

        if ($response->successful()) {
            return $this->jsonObject($response);
        }

        if ($response->status() === 400) {
            $order = $this->order($orderId);

            if (($order['paid'] ?? false) === true) {
                return $order;
            }
        }

        $this->throwFrom($response, "/partner/order/{$orderId}/confirm");
    }

    public function cancelOrder(int $orderId): void
    {
        $this->send('DELETE', "/partner/order/{$orderId}/cancel");
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(string $method, string $path, array $body = [], bool $throw = true): Response
    {
        if (! $this->isConfigured()) {
            throw new DssSoftException('DSS-Soft не настроен: нет токена или ключа.');
        }

        $path = '/'.ltrim($path, '/');
        $timestamp = (string) now()->timestamp;
        $request = Http::baseUrl((string) config('services.dss_soft.base_url'))
            ->connectTimeout((int) config('services.dss_soft.connect_timeout', 3))
            ->timeout((int) config('services.dss_soft.timeout', 10))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-PARTNER-TOKEN' => (string) config('services.dss_soft.token'),
                'TIMESTAMP' => $timestamp,
                'SIGNATURE' => $this->signature($path, $timestamp),
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
            'GET' => $request->get($path),
            'POST' => $request->post($path, $body),
            'PATCH' => $request->patch($path, $body),
            'DELETE' => $request->delete($path),
            default => throw new DssSoftException("Неподдерживаемый метод DSS-Soft: {$method}"),
        };

        if ($throw && ! $response->successful()) {
            $this->throwFrom($response, $path);
        }

        return $response;
    }

    private function signature(string $path, string $timestamp): string
    {
        $key = $this->publicKey();
        $encrypted = '';

        if (! openssl_public_encrypt($path.$timestamp, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new DssSoftException('Не удалось подписать запрос к DSS-Soft.');
        }

        return base64_encode($encrypted);
    }

    private function publicKey(): OpenSSLAsymmetricKey
    {
        $pem = $this->publicKeyPem();
        $key = openssl_pkey_get_public($pem);

        if ($key instanceof OpenSSLAsymmetricKey) {
            return $key;
        }

        $private = openssl_pkey_get_private($pem);

        if ($private instanceof OpenSSLAsymmetricKey) {
            $details = openssl_pkey_get_details($private);
            $extracted = is_array($details) ? openssl_pkey_get_public($details['key'] ?? '') : false;

            if ($extracted instanceof OpenSSLAsymmetricKey) {
                return $extracted;
            }
        }

        throw new DssSoftException('Не удалось прочитать ключ DSS-Soft.');
    }

    private function publicKeyPem(): string
    {
        $inline = trim((string) config('services.dss_soft.public_key'));

        if ($inline !== '') {
            return $this->normalizePem($inline);
        }

        $path = (string) config('services.dss_soft.public_key_path');

        if ($path !== '' && ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        if ($path !== '' && is_readable($path)) {
            return $this->normalizePem((string) file_get_contents($path));
        }

        return '';
    }

    private function normalizePem(string $pem): string
    {
        if (preg_match('/-----BEGIN ([A-Z ]+)-----(.*?)-----END \1-----/s', $pem, $matches) !== 1) {
            return $pem;
        }

        $body = preg_replace('/\s+/', '', $matches[2]) ?? '';

        return "-----BEGIN {$matches[1]}-----\n".trim(chunk_split($body, 64, "\n"))."\n-----END {$matches[1]}-----\n";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonList(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            return [];
        }

        if (array_is_list($json)) {
            return $json;
        }

        $data = $json['data'] ?? null;

        return is_array($data) && array_is_list($data) ? $data : [];
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
        $message = $response->json('message')
            ?? $response->json('error')
            ?? 'Билетная система временно недоступна';

        $status = in_array($response->status(), [400, 404, 422], true) ? 422 : 502;

        throw new DssSoftException((string) $message, $status, $path);
    }
}
