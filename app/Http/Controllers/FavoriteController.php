<?php

namespace App\Http\Controllers;

use App\Http\Resources\EstablishmentDetailResource;
use App\Models\Establishment;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Favorites', weight: 3)]
class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $request->attributes->set('user_latitude', isset($validated['lat']) ? (float) $validated['lat'] : null);
        $request->attributes->set('user_longitude', isset($validated['lng']) ? (float) $validated['lng'] : null);

        $user = $request->user();
        $establishments = $user->favoriteEstablishments()
            ->active()
            ->with([
                'favoritedBy' => fn ($query) => $query->whereKey($user->id),
            ])
            ->get();

        return response()->json([
            'establishments' => EstablishmentDetailResource::collection($establishments),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'establishment_id' => ['required', 'integer'],
        ]);

        $establishment = Establishment::query()
            ->active()
            ->findOrFail($validated['establishment_id']);

        $request->user()->favoriteEstablishments()->syncWithoutDetaching([$establishment->id]);

        return response()->json([
            'establishment_id' => $establishment->id,
            'is_favorite' => true,
        ]);
    }

    public function destroy(Request $request, Establishment $establishment): JsonResponse
    {
        $request->user()->favoriteEstablishments()->detach($establishment->id);

        return response()->json(null, 204);
    }
}
