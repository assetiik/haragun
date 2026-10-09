<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileScreenResource;
use App\Http\Resources\UserResource;
use App\Models\Order;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Profile', weight: 2)]
class ProfileController extends Controller
{
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function show(Request $request): ProfileScreenResource
    {
        $user = $request->user();

        $recentOrders = Order::query()
            ->with(['establishment', 'items.product'])
            ->forUser($user->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return new ProfileScreenResource($user, $recentOrders);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->safe()->only(['name', 'notifications_enabled']));

        $recentOrders = Order::query()
            ->with(['establishment', 'items.product'])
            ->forUser($user->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json([
            'user' => new UserResource($user->fresh()),
            'profile' => new ProfileScreenResource($user->fresh(), $recentOrders),
        ]);
    }
}
