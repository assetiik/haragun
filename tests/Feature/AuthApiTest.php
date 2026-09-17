<?php

namespace Tests\Feature;

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
            ->assertJsonPath('message', 'Код отправлен')
            ->assertJsonStructure(['message', 'debug_code']);

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

        $code = OtpCode::query()->latest()->firstOrFail();
        $plainCode = '1234';
        $code->update(['code' => Hash::make($plainCode)]);

        $response = $this->postJson('/api/auth/verify-code', [
            'phone' => '77784046889',
            'code' => $plainCode,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'user' => ['id', 'name', 'phone', 'phone_formatted', 'initials', 'needs_name'],
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

        $otp = OtpCode::query()->latest()->firstOrFail();
        $plainCode = '4321';
        $otp->update(['code' => Hash::make($plainCode)]);

        $this->postJson('/api/auth/verify-code', [
            'phone' => '77071234567',
            'code' => $plainCode,
        ])
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'phone', 'phone_formatted', 'initials', 'needs_name'],
            ])
            ->assertJsonPath('user.phone_formatted', '+7 707 123 45 67')
            ->assertJsonPath('user.needs_name', true);
    }

    public function test_logout_deletes_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Вы вышли из аккаунта');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
