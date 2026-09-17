<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function send(string $phone): string
    {
        $plainCode = (string) random_int(1000, 9999);

        OtpCode::query()->create([
            'phone' => $phone,
            'code' => Hash::make($plainCode),
            'expires_at' => now()->addMinutes(5),
        ]);

        Log::info("OTP for {$phone}: {$plainCode}");

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
}
