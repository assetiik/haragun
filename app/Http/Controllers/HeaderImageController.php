<?php

namespace App\Http\Controllers;

use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Models\City;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Home', weight: 0)]
class HeaderImageController extends Controller
{
    #[Endpoint(
        title: 'Шапка (карусель)',
        description: 'Слайды верхней шапки на главной: фото, заголовок, подзаголовок и кнопка. Не путать с промо-плитками.',
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ]);

        $cityId = $this->resolveCityId($validated['city_id'] ?? null);

        $items = Banner::query()
            ->active()
            ->hero()
            ->forCity($cityId)
            ->ordered()
            ->get();

        return response()->json([
            'section' => 'header',
            'city_id' => $cityId,
            'items' => BannerResource::collection($items),
        ]);
    }

    private function resolveCityId(?int $cityId): ?int
    {
        if ($cityId !== null) {
            return $cityId;
        }

        return City::query()->active()->ordered()->value('id');
    }
}
