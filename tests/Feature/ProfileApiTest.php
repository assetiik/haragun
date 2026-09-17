<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Аружан',
            'phone' => '77071234567',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('name', 'Аружан')
            ->assertJsonPath('phone', '77071234567')
            ->assertJsonPath('phone_formatted', '+7 707 123 45 67')
            ->assertJsonPath('initials', 'А')
            ->assertJsonPath('needs_name', false);
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
            ->assertJsonPath('message', 'Профиль обновлён')
            ->assertJsonPath('user.name', 'Аружан')
            ->assertJsonPath('user.phone', '77071234567')
            ->assertJsonPath('user.phone_formatted', '+7 707 123 45 67')
            ->assertJsonPath('user.needs_name', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Аружан',
            'phone' => '77071234567',
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
