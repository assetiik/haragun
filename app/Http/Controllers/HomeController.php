<?php

namespace App\Http\Controllers;

use App\Enums\EstablishmentType;
use App\Http\Resources\BannerResource;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CityResource;
use App\Http\Resources\CoffeeLoyaltyResource;
use App\Http\Resources\PromoTileResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\WelcomeCoffeeGiftResource;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\City;
use App\Models\PromoTile;
use App\Models\User;
use App\Services\CoffeeLoyaltyService;
use App\Services\WelcomeCoffeeGiftService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[Group('Home', weight: 0)]
class HomeController extends Controller
{
    public function __construct(
        private CoffeeLoyaltyService $coffeeLoyalty,
        private WelcomeCoffeeGiftService $welcomeGifts,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $city = isset($validated['city_id'])
            ? City::query()->active()->findOrFail($validated['city_id'])
            : City::query()->active()->ordered()->first();

        abort_if($city === null, 404);

        $latitude = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $longitude = isset($validated['lng']) ? (float) $validated['lng'] : null;

        $request->attributes->set('user_latitude', $latitude);
        $request->attributes->set('user_longitude', $longitude);

        $headerImages = Banner::query()
            ->active()
            ->hero()
            ->forCity($city->id)
            ->ordered()
            ->get();

        $promoTiles = PromoTile::query()
            ->active()
            ->forCity($city->id)
            ->ordered()
            ->get();

        $brands = Brand::query()
            ->with(['establishments' => fn ($query) => $query->active()->ordered()])
            ->active()
            ->where('city_id', $city->id)
            ->ordered()
            ->get()
            ->filter(fn (Brand $brand) => $brand->establishments->isNotEmpty())
            ->values();

        if ($latitude !== null && $longitude !== null) {
            $brands = $brands
                ->sortBy(function (Brand $brand) use ($latitude, $longitude) {
                    $nearest = $brand->nearestEstablishment($latitude, $longitude);

                    return $nearest?->distanceKm($latitude, $longitude) ?? PHP_FLOAT_MAX;
                })
                ->values();
        }

        $cities = City::query()->active()->ordered()->get();
        $haragun = $brands
            ->filter(fn (Brand $brand): bool => $brand->type === EstablishmentType::IceArena)
            ->values();
        $cafes = $brands
            ->filter(fn (Brand $brand): bool => $brand->type === EstablishmentType::Coffee)
            ->values();

        /** @var User|null $user */
        $user = Auth::guard('sanctum')->user();
        $loyalty = $this->coffeeLoyalty->progressFor($user);
        $welcomeGift = $this->welcomeGifts->payloadFor($user);

        return response()->json([
            'app_name' => config('app.name'),
            'city' => new CityResource($city),
            'cities' => CityResource::collection($cities),
            'header_images' => BannerResource::collection($headerImages),
            'promo_tiles' => PromoTileResource::collection($promoTiles),
            'haragun' => BrandResource::collection($haragun),
            'cafes' => BrandResource::collection($cafes),
            'brands' => BrandResource::collection($brands),
            'coffee_loyalty' => $loyalty !== null ? new CoffeeLoyaltyResource($loyalty) : null,
            'welcome_gift' => $welcomeGift !== null
                ? new WelcomeCoffeeGiftResource($welcomeGift)
                : null,
            'user' => $user ? new UserResource($user) : null,
        ]);
    }
}
