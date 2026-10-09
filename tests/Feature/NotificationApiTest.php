<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/notifications/read')->assertUnauthorized();
    }

    public function test_order_status_changes_are_listed_for_the_user(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Preparing,
            'number' => 4821,
        ]);

        $order->update(['status' => OrderStatus::Ready]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 2)
            ->assertJsonPath('notifications.0.type', 'order_status')
            ->assertJsonPath('notifications.0.status', 'ready')
            ->assertJsonPath('notifications.0.order_id', $order->id)
            ->assertJsonPath('notifications.0.order_number', 4821)
            ->assertJsonPath('notifications.0.order_type', 'venue')
            ->assertJsonPath('notifications.0.establishment_id', $order->establishment_id)
            ->assertJsonPath('notifications.0.read_at', null)
            ->assertJsonPath('notifications.1.status', 'preparing');
    }

    public function test_disabled_notifications_are_not_recorded(): void
    {
        $user = User::factory()->create([
            'notifications_enabled' => false,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseCount('user_notifications', 0);
    }

    public function test_user_can_mark_one_and_then_all_notifications_read(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Accepted,
        ]);
        $order->update(['status' => OrderStatus::Ready]);

        Sanctum::actingAs($user);

        $readyId = UserNotification::query()->where('status', OrderStatus::Ready)->value('id');

        $this->postJson("/api/notifications/{$readyId}/read")
            ->assertOk()
            ->assertJsonPath('id', $readyId)
            ->assertJsonPath('status', 'ready');

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->postJson('/api/notifications/read')
            ->assertNoContent();

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $owner = User::factory()->create();
        $notification = UserNotification::factory()->create([
            'user_id' => $owner->id,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertDatabaseHas('user_notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }
}
