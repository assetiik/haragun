<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_manage_favorites(): void
    {
        $establishment = Establishment::factory()->create();

        $this->getJson('/api/favorites')->assertUnauthorized();
        $this->postJson('/api/favorites', [
            'establishment_id' => $establishment->id,
        ])->assertUnauthorized();
        $this->deleteJson("/api/favorites/{$establishment->id}")->assertUnauthorized();
    }

    public function test_user_can_save_list_and_remove_a_favorite(): void
    {
        $user = User::factory()->create();
        $establishment = Establishment::factory()->create([
            'name' => 'Кофейня «Москва»',
            'address' => 'ул. Сейфуллина 154',
        ]);
        $other = Establishment::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/favorites', [
            'establishment_id' => $establishment->id,
        ])
            ->assertOk()
            ->assertJsonPath('establishment_id', $establishment->id)
            ->assertJsonPath('is_favorite', true);

        $this->postJson('/api/favorites', [
            'establishment_id' => $establishment->id,
        ])->assertOk();

        $this->assertDatabaseCount('favorites', 1);

        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('establishments.0.id', $establishment->id)
            ->assertJsonPath('establishments.0.name', 'Кофейня «Москва»')
            ->assertJsonPath('establishments.0.is_favorite', true)
            ->assertJsonCount(1, 'establishments');

        $this->getJson("/api/establishments/{$establishment->id}")
            ->assertOk()
            ->assertJsonPath('establishment.is_favorite', true);

        $this->getJson("/api/establishments/{$other->id}")
            ->assertOk()
            ->assertJsonPath('establishment.is_favorite', false);

        $this->deleteJson("/api/favorites/{$establishment->id}")
            ->assertNoContent();

        $this->assertDatabaseCount('favorites', 0);

        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('establishments', []);
    }

    public function test_user_cannot_favorite_an_inactive_establishment(): void
    {
        $establishment = Establishment::factory()->create([
            'is_active' => false,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/favorites', [
            'establishment_id' => $establishment->id,
        ])->assertNotFound();
    }

    public function test_favorites_belong_only_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $establishment = Establishment::factory()->create();

        Sanctum::actingAs($owner);

        $this->postJson('/api/favorites', [
            'establishment_id' => $establishment->id,
        ])->assertOk();

        Sanctum::actingAs($other);

        $this->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonPath('establishments', []);

        $this->getJson("/api/establishments/{$establishment->id}")
            ->assertOk()
            ->assertJsonPath('establishment.is_favorite', false);
    }
}
