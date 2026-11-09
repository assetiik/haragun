<?php

namespace App\Services;

use App\Models\OtpCode;
use App\Services\Mobizon\MobizonClient;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(private MobizonClient $mobizon) {}

    public function send(string $phone): string
    {
        $plainCode = $this->plainCode();
        $message = str_replace(
            '{code}',
            $plainCode,
            (string) config('services.otp.message', 'Ваш код для входа в Haragun: {code}'),
        );

        if ($this->shouldSendSms()) {
            $this->mobizon->sendSms($phone, $message);
        } else {
            Log::info("OTP for {$phone}: {$plainCode}");
        }

        OtpCode::query()->create([
            'phone' => $phone,
            'code' => Hash::make($plainCode),
            'expires_at' => now()->addMinutes(5),
        ]);

        return $plainCode;
    }

    public function verify(string $phone, string $code): bool
    {
        $otp = OtpCode::query()
            ->where('phone', $phone)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $otp || ! Hash::check($code, $otp->code)) {
            return false;
        }

        $otp->update([
            'used_at' => now(),
        ]);

        return true;
    }

    private function shouldSendSms(): bool
    {
        return $this->mobizon->isConfigured()
            && blank(config('services.otp.static_code'));
    }

    private function plainCode(): string
    {
        $staticCode = config('services.otp.static_code');

        if (filled($staticCode)) {
            return (string) $staticCode;
        }

        return (string) random_int(1000, 9999);
    }
}
