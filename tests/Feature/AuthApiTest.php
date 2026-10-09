<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_code_stores_hashed_otp_and_returns_debug_code_in_local(): void
    {
        $response = $this->postJson('/api/auth/send-code', [
            'phone' => '+7 (778) 404-68-89',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('sent', true)
            ->assertJsonPath('debug_code', '1234');

        $this->assertDatabaseHas('otp_codes', [
            'phone' => '77784046889',
        ]);

        $otp = OtpCode::query()->first();

        $this->assertNotNull($otp);
        $this->assertTrue(Hash::check($response->json('debug_code'), $otp->code));
    }

    public function test_send_code_rejects_invalid_phone(): void
    {
        $this->postJson('/api/auth/send-code', [
            'phone' => '123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_verify_code_creates_user_and_returns_token(): void
    {
        $this->postJson('/api/auth/send-code', [
            'phone' => '77784046889',
        ]);

        $response = $this->postJson('/api/auth/verify-code', [
            'phone' => '77784046889',
            'code' => '1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'phone', 'needs_name'],
                'needs_name',
            ])
            ->assertJsonPath('user.phone', '77784046889')
            ->assertJsonPath('needs_name', true);

        $this->assertDatabaseHas('users', [
            'phone' => '77784046889',
            'name' => null,
        ]);
    }

    public function test_verify_code_rejects_wrong_code(): void
    {
        $this->postJson('/api/auth/send-code', [
            'phone' => '77784046889',
        ]);

        $this->postJson('/api/auth/verify-code', [
            'phone' => '77784046889',
            'code' => '0000',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Неверный или просроченный код');
    }

    public function test_verify_response_includes_profile_fields(): void
    {
        $this->postJson('/api/auth/send-code', [
            'phone' => '77071234567',
        ]);

        $this->postJson('/api/auth/verify-code', [
            'phone' => '77071234567',
            'code' => '1234',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'phone', 'needs_name'],
            ])
            ->assertJsonPath('user.phone', '77071234567')
            ->assertJsonPath('user.needs_name', true);
    }

    public function test_logout_deletes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guest_cannot_delete_account(): void
    {
        $this->deleteJson('/api/auth/account')->assertUnauthorized();
    }

    public function test_authenticated_user_can_delete_own_account(): void
    {
        $user = User::factory()->create([
            'phone' => '77071234567',
        ]);
        $other = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);
        Order::factory()->create([
            'user_id' => $other->id,
        ]);
        OtpCode::query()->create([
            'phone' => $user->phone,
            'code' => Hash::make('1234'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->withToken($token)
            ->deleteJson('/api/auth/account')
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        $this->assertDatabaseMissing('otp_codes', ['phone' => '77071234567']);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_deleted_phone_can_register_again(): void
    {
        $user = User::factory()->create([
            'phone' => '77071234567',
            'name' => 'Аружан',
        ]);
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/auth/account')
            ->assertNoContent();

        $this->postJson('/api/auth/send-code', [
            'phone' => '77071234567',
        ])->assertOk();

        $this->postJson('/api/auth/verify-code', [
            'phone' => '77071234567',
            'code' => '1234',
        ])
            ->assertOk()
            ->assertJsonPath('needs_name', true)
            ->assertJsonPath('user.phone', '77071234567');

        $this->assertDatabaseHas('users', [
            'phone' => '77071234567',
            'name' => null,
        ]);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
