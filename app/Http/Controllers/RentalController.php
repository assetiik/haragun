<?php

namespace App\Http\Controllers;

use App\Http\Resources\RentalProductResource;
use App\Models\Establishment;
use Carbon\Carbon;
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

        $rentals = $establishment->products()
            ->rentals()
            ->active()
            ->ordered()
            ->with(['skateSizes' => fn ($query) => $query->ordered()])
            ->get();

        $sessionDate = Carbon::parse($date);

        return response()->json([
            'establishment' => [
                'id' => $establishment->id,
                'name' => $establishment->name,
                'short_name' => str_contains($establishment->name, 'Ледовая арена')
                    ? 'Ледовая арена'
                    : $establishment->name,
            ],
            'session' => [
                'date' => $date,
                'time' => $time,
                'label' => $this->sessionLabel($sessionDate, $time),
            ],
            'title' => 'Аренда',
            'subtitle' => 'Выдаём на стойке по коду брони',
            'rentals' => RentalProductResource::collection($rentals),
        ]);
    }

    private function sessionLabel(Carbon $date, string $time): string
    {
        $weekdays = [0 => 'вс', 1 => 'пн', 2 => 'вт', 3 => 'ср', 4 => 'чт', 5 => 'пт', 6 => 'сб'];
        $months = [
            1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
            5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
            9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
        ];

        return "{$weekdays[$date->dayOfWeek]}, {$date->day} {$months[$date->month]} · {$time}";
    }
}
