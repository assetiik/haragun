<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderReadyPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_coffee_order_becomes_preparing_after_a_few_seconds(): void
    {
        Carbon::setTestNow('2026-10-15 12:00:00');

        $order = Order::factory()->create([
            'type' => OrderType::Venue,
            'status' => OrderStatus::Accepted,
            'accepted_at' => now(),
            'preparing_at' => null,
        ]);

        $this->assertSame(OrderStatus::Accepted, Order::query()->find($order->id)->status);

        Carbon::setTestNow(now()->addSeconds(Order::PREPARING_AFTER_SECONDS + 1));

        $promoted = Order::query()->find($order->id);

        $this->assertSame(OrderStatus::Preparing, $promoted->status);
        $this->assertNotNull($promoted->preparing_at);
    }

    public function test_client_cannot_mark_an_order_ready(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Accepted,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/orders/{$order->id}/mark-ready")
            ->assertNotFound();

        $this->assertSame(OrderStatus::Accepted, $order->fresh()->status);
    }

    public function test_marking_a_coffee_order_ready_notifies_the_client_device(): void
    {
        Cache::flush();
        $credentials = $this->firebaseCredentials();
        config([
            'services.fcm.project_id' => 'haragun-test',
            'services.fcm.credentials' => $credentials,
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'ya29.test']),
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/haragun-test/messages/1']),
        ]);

        $user = User::factory()->create([
            'fcm_token' => 'device-token',
            'notifications_enabled' => true,
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Preparing,
            'guest_number' => 6757,
            'number' => 217,
        ]);

        $order->markReady();

        $this->assertSame(OrderStatus::Ready, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->ready_at);
        $this->assertTrue(
            UserNotification::query()
                ->where('user_id', $user->id)
                ->where('order_id', $order->id)
                ->where('status', OrderStatus::Ready)
                ->exists()
        );

        Http::assertSent(function ($request) use ($order): bool {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/haragun-test/messages:send'
                && $request['message']['token'] === 'device-token'
                && $request['message']['notification']['title'] === 'Заказ готов'
                && $request['message']['data']['order_id'] === (string) $order->id
                && $request['message']['data']['screen'] === 'ready';
        });
    }

    public function test_profile_stores_the_device_token_used_for_the_push(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', [
            'fcm_token' => 'device-token',
        ])->assertOk();

        $this->assertSame('device-token', $user->fresh()->fcm_token);
    }

    private function firebaseCredentials(): string
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $private = '';
        openssl_pkey_export($key, $private);

        $path = storage_path('framework/testing-firebase.json');
        file_put_contents($path, json_encode([
            'client_email' => 'fcm@haragun-test.iam.gserviceaccount.com',
            'private_key' => $private,
        ]));

        return $path;
    }
}
