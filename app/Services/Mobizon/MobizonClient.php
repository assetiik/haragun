<?php

namespace App\Services\Mobizon;

use App\Exceptions\MobizonException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class MobizonClient
{
    private const SEND_PATH = '/service/message/sendSmsMessage';

    public function isConfigured(): bool
    {
        return filled(config('services.mobizon.api_key'));
    }

    public function sendSms(string $recipient, string $text): void
    {
        if (! $this->isConfigured()) {
            throw new MobizonException('Mobizon не настроен: нет API-ключа.');
        }

        $payload = [
            'recipient' => preg_replace('/\D+/', '', $recipient) ?? '',
            'text' => $text,
        ];

        $from = config('services.mobizon.from');

        if (filled($from)) {
            $payload['from'] = (string) $from;
        }

        try {
            $response = $this->request()
                ->asForm()
                ->post(self::SEND_PATH, $payload);
        } catch (ConnectionException $exception) {
            throw new MobizonException(
                'Не удалось связаться с Mobizon.',
                502,
                self::SEND_PATH,
            );
        }

        if (! $response->successful()) {
            throw new MobizonException(
                Str::limit((string) ($response->json('message') ?? 'SMS-шлюз временно недоступен'), 500),
                502,
                self::SEND_PATH,
            );
        }

        $code = (int) $response->json('code', 1);

        if ($code !== 0) {
            $message = $response->json('message')
                ?? $response->json('data')
                ?? 'Не удалось отправить SMS';

            if (is_array($message)) {
                $message = json_encode($message, JSON_UNESCAPED_UNICODE) ?: 'Не удалось отправить SMS';
            }

            throw new MobizonException(
                Str::limit((string) $message, 500),
                502,
                self::SEND_PATH,
            );
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.mobizon.base_url'))
            ->connectTimeout((int) config('services.mobizon.connect_timeout', 3))
            ->timeout((int) config('services.mobizon.timeout', 10))
            ->acceptJson()
            ->withQueryParameters([
                'output' => 'json',
                'api' => 'v1',
                'apiKey' => (string) config('services.mobizon.api_key'),
            ])
            ->retry(
                [100, 500],
                0,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                false,
            );
    }
}
