<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class StoredUrl
{
    public static function public(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return Storage::disk('public')->url(ltrim($value, '/'));
    }
}
