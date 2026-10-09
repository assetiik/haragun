<?php

namespace App\Http\Controllers;

use App\Enums\AppLocale;
use App\Models\LocalizationString;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

#[Group('Localization', weight: 1)]
class LocalizationController extends Controller
{
    #[Endpoint(
        title: 'Словарь приложения',
        description: 'Все строки интерфейса на ru, kz и en. Параметр locale оставляет один язык.',
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['nullable', 'string', Rule::enum(AppLocale::class)],
        ]);

        $locale = isset($validated['locale'])
            ? AppLocale::from($validated['locale'])
            : null;

        return response()->json(LocalizationString::dictionary($locale));
    }

    #[Endpoint(
        title: 'Словарь одного языка',
        description: 'Строки интерфейса для locale: ru, kz или en. Ключ вида section.key.',
    )]
    public function show(string $locale): JsonResponse
    {
        $resolved = AppLocale::tryFrom($locale);

        if ($resolved === null) {
            throw ValidationException::withMessages([
                'locale' => 'Поддерживаются языки: ru, kz, en.',
            ]);
        }

        return response()->json(LocalizationString::dictionary($resolved));
    }
}
