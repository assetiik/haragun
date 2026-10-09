<?php

namespace Tests\Feature;

use App\Enums\EstablishmentType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Establishment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/profile')->assertUnauthorized();
        $this->patchJson('/api/profile', ['name' => 'Аружан'])->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_profile_screen(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-23 13:00:00'));

        $user = User::factory()->create([
            'name' => 'Аружан',
            'phone' => '77071234567',
            'notifications_enabled' => true,
        ]);

        $coffee = Establishment::factory()->create([
            'type' => EstablishmentType::Coffee,
            'address' => 'ул. Кунаева, 12',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'establishment_id' => $coffee->id,
            'type' => OrderType::Venue,
            'status' => OrderStatus::Completed,
            'total' => 9560,
            'summary_short' => '3 напитка',
            'paid_at' => now(),
            'completed_at' => now(),
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Латте',
            'quantity' => 1,
            'image_url' => 'https://example.com/latte.jpg',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'name' => 'Капучино',
            'quantity' => 1,
            'image_url' => 'https://example.com/cappuccino.jpg',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Аружан')
            ->assertJsonPath('user.phone', '77071234567')
            ->assertJsonPath('user.notifications_enabled', true)
            ->assertJsonPath('orders.0.total', 9560)
            ->assertJsonPath('orders.0.thumbnails.0', 'https://example.com/latte.jpg')
            ->assertJsonPath('orders.0.thumbnails.1', 'https://example.com/cappuccino.jpg')
            ->assertJsonPath('notifications_enabled', true)
            ->assertJsonPath('language', 'ru')
            ->assertJsonPath('about.title', 'О приложении')
            ->assertJsonPath('contacts.title', 'Контакты')
            ->assertJsonPath('contacts.email', 'support@haragun.kz');

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('notifications_enabled', true);
    }

    public function test_authenticated_user_can_update_name(): void
    {
        $user = User::factory()->withoutName()->create([
            'phone' => '77071234567',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', [
            'name' => 'Аружан',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Аружан')
            ->assertJsonPath('user.phone', '77071234567')
            ->assertJsonPath('user.needs_name', false)
            ->assertJsonPath('profile.user.name', 'Аружан');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Аружан',
            'phone' => '77071234567',
        ]);
    }

    public function test_authenticated_user_can_toggle_notifications(): void
    {
        $user = User::factory()->create([
            'notifications_enabled' => true,
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', [
            'notifications_enabled' => false,
        ])
            ->assertOk()
            ->assertJsonPath('user.notifications_enabled', false)
            ->assertJsonPath('profile.notifications_enabled', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'notifications_enabled' => false,
        ]);
    }

    public function test_update_profile_rejects_empty_name(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/profile', [
            'name' => '',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
