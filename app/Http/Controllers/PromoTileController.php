<?php

namespace App\Http\Controllers;

use App\Http\Resources\PromoTileResource;
use App\Models\City;
use App\Models\PromoTile;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Home', weight: 0)]
class PromoTileController extends Controller
{
    #[Endpoint(
        title: 'Промо-плитки',
        description: 'Нижние плитки на главной: только фото и короткий текст. Не путать с шапкой.',
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ]);

        $cityId = $this->resolveCityId($validated['city_id'] ?? null);

        $items = PromoTile::query()
            ->active()
            ->forCity($cityId)
            ->ordered()
            ->get();

        return response()->json([
            'section' => 'promo_tiles',
            'city_id' => $cityId,
            'items' => PromoTileResource::collection($items),
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
