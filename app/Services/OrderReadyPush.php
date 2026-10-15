<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderReadyPush
{
    public function send(Order $order): void
    {
        $order->loadMissing(['user', 'establishment']);

        $user = $order->user;
        $token = $user?->fcm_token;

        if ($user === null || ! $user->notifications_enabled || ! is_string($token) || $token === '') {
            return;
        }

        $projectId = config('services.fcm.project_id');
        $credentials = $this->credentials();

        if (! is_string($projectId) || $projectId === '' || $credentials === null) {
            Log::info('Order ready push skipped: Firebase is not configured.', [
                'order_id' => $order->id,
            ]);

            return;
        }

        try {
            $accessToken = $this->accessToken($credentials);

            Http::withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(5)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => 'Заказ готов',
                            'body' => 'Подойдите к стойке. '.$order->guestLabel(),
                        ],
                        'data' => [
                            'order_id' => (string) $order->id,
                            'screen' => 'ready',
                        ],
                    ],
                ])
                ->throw();
        } catch (Throwable $exception) {
            Log::error('Order ready push failed.', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{client_email: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        $path = config('services.fcm.credentials');

        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        if (! is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! is_string($decoded['client_email'] ?? null) || ! is_string($decoded['private_key'] ?? null)) {
            return null;
        }

        return [
            'client_email' => $decoded['client_email'],
            'private_key' => $decoded['private_key'],
        ];
    }

    /**
     * @param  array{client_email: string, private_key: string}  $credentials
     */
    private function accessToken(array $credentials): string
    {
        return Cache::remember('fcm.access_token', 3000, function () use ($credentials): string {
            $now = time();
            $header = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = $this->base64Url((string) json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $unsigned = $header.'.'.$claim;

            $signature = '';
            $signed = openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

            if (! $signed) {
                throw new \RuntimeException('Firebase credentials could not be signed.');
            }

            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(5)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $unsigned.'.'.$this->base64Url($signature),
                ])
                ->throw();

            $token = $response->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new \RuntimeException('Firebase did not return an access token.');
            }

            return $token;
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
