<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserNotificationResource;
use App\Models\UserNotification;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Notifications', weight: 3)]
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->get();

        return response()->json([
            'unread_count' => $notifications->whereNull('read_at')->count(),
            'notifications' => UserNotificationResource::collection($notifications),
        ]);
    }

    public function read(Request $request, UserNotification $userNotification): UserNotificationResource
    {
        abort_unless($userNotification->user_id === $request->user()->id, 404);

        if ($userNotification->read_at === null) {
            $userNotification->update(['read_at' => now()]);
        }

        return new UserNotificationResource($userNotification);
    }

    public function readAll(Request $request): JsonResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(null, 204);
    }
}
