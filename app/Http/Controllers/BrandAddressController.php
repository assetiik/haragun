<?php

namespace App\Http\Controllers;

use App\Http\Resources\BrandAddressResource;
use App\Models\Brand;
use App\Models\Establishment;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Brands', weight: 3)]
class BrandAddressController extends Controller
{
    public function index(Request $request, Brand $brand): JsonResponse
    {
        abort_unless($brand->is_active, 404);

        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'selected_id' => ['nullable', 'integer', 'exists:establishments,id'],
        ]);

        $latitude = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $longitude = isset($validated['lng']) ? (float) $validated['lng'] : null;

        $request->attributes->set('user_latitude', $latitude);
        $request->attributes->set('user_longitude', $longitude);

        $addresses = $brand->establishments()
            ->active()
            ->ordered()
            ->get();

        abort_if($addresses->isEmpty(), 404, 'Нет адресов');

        if ($latitude !== null && $longitude !== null) {
            $addresses = $addresses
                ->sortBy(fn (Establishment $establishment) => $establishment->distanceKm($latitude, $longitude) ?? PHP_FLOAT_MAX)
                ->values();
        }

        $selectedId = null;
        if (isset($validated['selected_id'])) {
            $selectedId = $addresses->firstWhere('id', (int) $validated['selected_id'])?->id;
        }
        $selectedId ??= $addresses->first()?->id;

        $request->attributes->set('selected_address_id', $selectedId);
        $selected = $addresses->firstWhere('id', $selectedId) ?? $addresses->first();

        return response()->json([
            'brand' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'type' => $brand->type->value,
                'icon_key' => $brand->icon_key,
                'accent_color' => $brand->accent_color,
            ],
            'selected_address_id' => $selected?->id,
            'addresses' => BrandAddressResource::collection($addresses),
            'map' => [
                'center' => [
                    'latitude' => $selected?->latitude,
                    'longitude' => $selected?->longitude,
                ],
                'markers' => $addresses->map(fn (Establishment $address) => [
                    'id' => $address->id,
                    'latitude' => $address->latitude,
                    'longitude' => $address->longitude,
                    'title' => $address->address,
                    'is_selected' => $selected?->id === $address->id,
                ])->values(),
            ],
            'selected_address' => $selected
                ? new BrandAddressResource($selected)
                : null,
        ]);
    }

    public function show(Request $request, Brand $brand, Establishment $establishment): BrandAddressResource
    {
        abort_unless($brand->is_active, 404);
        abort_unless($establishment->brand_id === $brand->id && $establishment->is_active, 404);

        $validated = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $request->attributes->set('user_latitude', isset($validated['lat']) ? (float) $validated['lat'] : null);
        $request->attributes->set('user_longitude', isset($validated['lng']) ? (float) $validated['lng'] : null);
        $request->attributes->set('selected_address_id', $establishment->id);

        return new BrandAddressResource($establishment);
    }
}
