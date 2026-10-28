<?php

namespace App\Services\DssSoft;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DssSoftCatalog
{
    /**
     * @var array<string, mixed>
     */
    private array $memo = [];

    public function __construct(private DssSoftClient $client) {}

    public function enabled(): bool
    {
        return $this->client->isConfigured();
    }

    public function usesDss(Establishment $establishment): bool
    {
        return $this->enabled() && $establishment->type === EstablishmentType::IceArena;
    }

    /**
     * @return list<array{date: string, weekday: int, day: int, is_available: bool, dss_day_id: int}>
     */
    public function dates(): array
    {
        return $this->remember('dates', function (): array {
            return array_values(array_filter(
                array_map(function (array $day): ?array {
                    $date = Carbon::parse((string) ($day['date'] ?? ''))->startOfDay();

                    if (! $date->isValid()) {
                        return null;
                    }

                    return [
                        'date' => $date->toDateString(),
                        'weekday' => $date->dayOfWeek,
                        'day' => $date->day,
                        'is_available' => true,
                        'dss_day_id' => (int) ($day['id'] ?? 0),
                    ];
                }, $this->client->days()),
            ));
        });
    }

    public function resolveDate(?string $date): ?string
    {
        $available = collect($this->dates())->pluck('date');

        if ($date !== null && $available->contains($date)) {
            return $date;
        }

        return $available->first();
    }

    /**
     * @return list<array{time: string, ends_at: string, is_available: bool, dss_slot_id: int}>
     */
    public function slots(string $date): array
    {
        $day = $this->dayOn($date);

        if ($day === null) {
            return [];
        }

        $now = now();

        return $this->remember('slots:'.$date, function () use ($date, $day, $now): array {
            return array_values(array_filter(
                array_map(function (array $slot) use ($date, $now): ?array {
                    $start = $this->clock((string) ($slot['start'] ?? $slot['title'] ?? ''));
                    $end = $this->clock((string) ($slot['end'] ?? ''));

                    if ($start === null) {
                        return null;
                    }

                    $endsAt = $end ?? $start;
                    $sessionEnd = Carbon::parse($date.' '.$endsAt.':00');

                    return [
                        'time' => $start,
                        'ends_at' => $endsAt,
                        'is_available' => $sessionEnd->gt($now),
                        'dss_slot_id' => (int) ($slot['id'] ?? 0),
                    ];
                }, $this->client->slots((int) $day['dss_day_id'])),
            ));
        });
    }

    /**
     * @return array{dss_day_id: int, dss_slot_id: int, time: string, ends_at: string, prices: list<array<string, mixed>>}
     */
    public function resolveSlot(string $date, string $time): array
    {
        $day = $this->dayOn($date);

        if ($day === null) {
            throw ValidationException::withMessages([
                'date' => ['На эту дату нет сеансов.'],
            ]);
        }

        $wanted = substr($time, 0, 5);
        $slot = collect($this->slots($date))->first(
            fn (array $item): bool => $item['time'] === $wanted && $item['is_available'],
        );

        if ($slot === null) {
            throw ValidationException::withMessages([
                'time' => ['Этот сеанс недоступен.'],
            ]);
        }

        $dayId = (int) $day['dss_day_id'];
        $slotId = (int) $slot['dss_slot_id'];

        return [
            'dss_day_id' => $dayId,
            'dss_slot_id' => $slotId,
            'time' => $slot['time'],
            'ends_at' => $slot['ends_at'],
            'prices' => $this->remember(
                "prices:{$dayId}:{$slotId}",
                fn (): array => $this->client->prices($dayId, $slotId),
            ),
        ];
    }

    /**
     * @return array{id: int, title: string, price: int}|null
     */
    public function priceGroupFor(Product $product, string $date, string $time): ?array
    {
        if ($product->kind !== ProductKind::Ticket) {
            return null;
        }

        $slot = $this->resolveSlot($date, $time);
        $name = mb_strtolower(trim($product->name));

        foreach ($slot['prices'] as $group) {
            $title = mb_strtolower(trim((string) ($group['title'] ?? '')));

            if ($title === '' || $name === '') {
                continue;
            }

            if ($title === $name || str_contains($title, $name) || str_contains($name, $title)) {
                return [
                    'id' => (int) ($group['id'] ?? 0),
                    'title' => (string) ($group['title'] ?? $product->name),
                    'price' => (int) ($group['price'] ?? 0),
                ];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return Collection<int, Product>
     */
    public function applyPrices(Collection $products, string $date, string $time): Collection
    {
        return $products->map(function (Product $product) use ($date, $time): Product {
            $group = $this->priceGroupFor($product, $date, $time);

            if ($group === null) {
                return $product;
            }

            $product->price = $group['price'];
            $product->weekend_price = $group['price'];

            return $product;
        });
    }

    /**
     * @return array{date: string, weekday: int, day: int, is_available: bool, dss_day_id: int}|null
     */
    private function dayOn(string $date): ?array
    {
        return collect($this->dates())->firstWhere('date', $date);
    }

    private function remember(string $key, callable $callback): mixed
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $callback();
    }

    private function clock(string $value): ?string
    {
        if (preg_match('/(\d{2}:\d{2})/', $value, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
