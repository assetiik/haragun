<?php

namespace App\Http\Controllers;

use App\Http\Resources\RentalProductResource;
use App\Models\Establishment;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Arena', weight: 4)]
class RentalController extends Controller
{
    public function show(Request $request, Establishment $establishment): JsonResponse
    {
        abort_unless($establishment->is_active, 404);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();
        $time = $validated['time'] ?? '16:00';

        $request->attributes->set('booking_date', $date);

        $rentals = $establishment->products()
            ->rentals()
            ->active()
            ->ordered()
            ->with(['skateSizes' => fn ($query) => $query->ordered()])
            ->get();

        return response()->json([
            'establishment' => [
                'id' => $establishment->id,
                'name' => $establishment->name,
            ],
            'session' => [
                'date' => $date,
                'time' => $time,
            ],
            'rentals' => RentalProductResource::collection($rentals),
        ]);
    }
}
