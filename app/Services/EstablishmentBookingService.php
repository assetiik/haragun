<?php

namespace App\Services;

use App\Models\Establishment;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class EstablishmentBookingService
{
    private const WEEKDAYS = [
        0 => 'вс',
        1 => 'пн',
        2 => 'вт',
        3 => 'ср',
        4 => 'чт',
        5 => 'пт',
        6 => 'сб',
    ];

    /**
     * @return list<array{date: string, weekday_short: string, day: int, is_available: bool}>
     */
    public function dates(Establishment $establishment, ?CarbonInterface $from = null): array
    {
        $from ??= now()->startOfDay();
        $days = max(1, $establishment->booking_days_ahead);

        $dates = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i);
            $dates[] = [
                'date' => $date->toDateString(),
                'weekday_short' => self::WEEKDAYS[$date->dayOfWeek],
                'day' => $date->day,
                'is_available' => true,
            ];
        }

        return $dates;
    }

    /**
     * @return list<array{time: string, is_available: bool}>
     */
    public function slots(Establishment $establishment, CarbonInterface|string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $opensAt = Carbon::parse($day->toDateString().' '.$establishment->formattedTime($establishment->opens_at));
        $closesAt = Carbon::parse($day->toDateString().' '.$establishment->formattedTime($establishment->closes_at));
        $interval = max(15, $establishment->slot_interval_minutes);
        $now = now();

        $blocked = $establishment->unavailableSlots()
            ->whereDate('date', $day->toDateString())
            ->get()
            ->map(fn ($slot) => $slot->timeLabel())
            ->all();

        $slots = [];
        $cursor = $opensAt->copy();

        while ($cursor->lt($closesAt)) {
            $time = $cursor->format('H:i');
            $isPast = $day->isSameDay($now) && $cursor->lte($now);
            $isBlocked = in_array($time, $blocked, true);

            $slots[] = [
                'time' => $time,
                'is_available' => ! $isPast && ! $isBlocked,
            ];

            $cursor->addMinutes($interval);
        }

        return $slots;
    }

    public function resolveDate(Establishment $establishment, ?string $date): string
    {
        $available = collect($this->dates($establishment))->pluck('date');

        if ($date !== null && $available->contains($date)) {
            return $date;
        }

        return $available->first();
    }
}
