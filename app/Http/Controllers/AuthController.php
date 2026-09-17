<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendCodeRequest;
use App\Http\Requests\VerifyCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Auth', weight: 1)]
class AuthController extends Controller
{
    public function __construct(private OtpService $otp) {}

    #[Endpoint(title: 'Отправить код', description: 'Шлёт SMS-код на номер телефона.')]
    public function sendCode(SendCodeRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $plainCode = $this->otp->send($phone);

        $payload = [
            'message' => 'Код отправлен',
        ];

        if (! app()->isProduction()) {
            $payload['debug_code'] = $plainCode;
        }

        return response()->json($payload);
    }

    #[Endpoint(title: 'Подтвердить код', description: 'Возвращает Bearer-токен для Flutter.')]
    public function verifyCode(VerifyCodeRequest $request): JsonResponse
    {
        $phone = $request->validated('phone');
        $code = $request->validated('code');

        if (! $this->otp->verify($phone, $code)) {
            return response()->json([
                'message' => 'Неверный или просроченный код',
                'errors' => [
                    'code' => ['Неверный или просроченный код'],
                ],
            ], 422);
        }

        $user = User::query()->firstOrCreate(
            ['phone' => $phone],
            ['name' => null],
        );

        $token = $user->createToken('flutter')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
            'needs_name' => blank($user->name),
        ]);
    }

    #[Endpoint(title: 'Выйти')]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Вы вышли из аккаунта',
        ]);
    }
}
