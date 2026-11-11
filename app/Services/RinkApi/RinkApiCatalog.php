<?php

namespace App\Services\RinkApi;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Establishment;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RinkApiCatalog
{
    private const BOOKING_DAYS = 30;

    /**
     * @var array<string, mixed>
     */
    private array $memo = [];

    public function __construct(private RinkApiClient $client) {}

    public function enabled(): bool
    {
        return config('services.ticket_provider') === 'rink_api'
            && $this->client->isConfigured();
    }

    public function usesRink(Establishment $establishment): bool
    {
        return $this->enabled() && $establishment->type === EstablishmentType::IceArena;
    }

    /**
     * @return list<array{date: string, weekday: int, day: int, is_available: bool}>
     */
    public function dates(): array
    {
        return $this->remember('dates', function (): array {
            $from = now()->startOfDay();
            $dates = [];

            for ($i = 0; $i < self::BOOKING_DAYS; $i++) {
                $date = $from->copy()->addDays($i);
                $dates[] = [
                    'date' => $date->toDateString(),
                    'weekday' => $date->dayOfWeek,
                    'day' => $date->day,
                    'is_available' => true,
                ];
            }

            return $dates;
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
     * @return list<array{
     *     time: string,
     *     ends_at: string,
     *     is_available: bool,
     *     rink_session_id: int,
     *     sale_state: string|null,
     *     entry_opens_at: string|null,
     *     entry_closes_at: string|null,
     *     prices: list<array{ticket_type: string, name: array<string, string>|string|null, price: int}>
     * }>
     */
    public function slots(string $date): array
    {
        return collect($this->daySessions($date))
            ->map(function (array $session): array {
                return [
                    'time' => $session['time'],
                    'ends_at' => $session['ends_at'],
                    'is_available' => $session['is_available'],
                    'rink_session_id' => $session['rink_session_id'],
                    'sale_state' => $session['sale_state'],
                    'entry_opens_at' => $session['entry_opens_at'],
                    'entry_closes_at' => $session['entry_closes_at'],
                    'prices' => $session['prices'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     rink_session_id: int,
     *     time: string,
     *     ends_at: string,
     *     entry_opens_at: string|null,
     *     entry_closes_at: string|null,
     *     prices: list<array{ticket_type: string, name: array<string, string>|string|null, price: int}>
     * }
     */
    public function resolveSession(string $date, string $time): array
    {
        $wanted = substr($time, 0, 5);
        $session = collect($this->daySessions($date))->first(
            fn (array $item): bool => $item['time'] === $wanted && $item['is_available'],
        );

        if ($session === null) {
            throw ValidationException::withMessages([
                'time' => ['Этот сеанс недоступен.'],
            ]);
        }

        return [
            'rink_session_id' => $session['rink_session_id'],
            'time' => $session['time'],
            'ends_at' => $session['ends_at'],
            'entry_opens_at' => $session['entry_opens_at'],
            'entry_closes_at' => $session['entry_closes_at'],
            'prices' => $session['prices'],
        ];
    }

    /**
     * @return list<array{
     *     time: string,
     *     ends_at: string,
     *     is_available: bool,
     *     rink_session_id: int,
     *     sale_state: string|null,
     *     entry_opens_at: string|null,
     *     entry_closes_at: string|null,
     *     prices: list<array{ticket_type: string, name: array<string, string>|string|null, price: int}>
     * }>
     */
    private function daySessions(string $date): array
    {
        return $this->remember('sessions:'.$date, function () use ($date): array {
            $payload = $this->client->sessions($date);
            $sessions = $payload['sessions'] ?? [];
            $now = now();

            if (! is_array($sessions)) {
                return [];
            }

            return array_values(array_filter(array_map(function (mixed $session) use ($now): ?array {
                if (! is_array($session)) {
                    return null;
                }

                $startsAt = $this->clockFromIso((string) ($session['starts_at'] ?? ''));
                $endsAt = $this->clockFromIso((string) ($session['ends_at'] ?? '')) ?? $startsAt;

                if ($startsAt === null || $endsAt === null) {
                    return null;
                }

                $sessionEnd = Carbon::parse(
                    Carbon::parse((string) ($session['starts_at'] ?? ''))->toDateString().' '.$endsAt.':00'
                );
                $onSale = ($session['on_sale'] ?? false) === true;

                $prices = [];

                foreach ($session['prices'] ?? [] as $price) {
                    if (! is_array($price) || blank($price['ticket_type'] ?? null)) {
                        continue;
                    }

                    $prices[] = [
                        'ticket_type' => (string) $price['ticket_type'],
                        'name' => $price['name'] ?? null,
                        'price' => (int) ($price['price'] ?? 0),
                    ];
                }

                return [
                    'time' => $startsAt,
                    'ends_at' => $endsAt,
                    'is_available' => $onSale && $sessionEnd->gt($now),
                    'rink_session_id' => (int) ($session['id'] ?? 0),
                    'sale_state' => isset($session['sale_state']) ? (string) $session['sale_state'] : null,
                    'entry_opens_at' => $this->clockFromIso((string) ($session['entry_opens_at'] ?? '')),
                    'entry_closes_at' => $this->clockFromIso((string) ($session['entry_closes_at'] ?? '')),
                    'prices' => $prices,
                ];
            }, $sessions)));
        });
    }

    /**
     * @return array{ticket_type: string, price: int}|null
     */
    public function priceFor(Product $product, string $date, string $time): ?array
    {
        if ($product->kind !== ProductKind::Ticket || blank($product->ticket_type)) {
            return null;
        }

        $session = $this->resolveSession($date, $time);
        $ticketType = (string) $product->ticket_type;

        foreach ($session['prices'] as $price) {
            if ($price['ticket_type'] === $ticketType) {
                return [
                    'ticket_type' => $ticketType,
                    'price' => $price['price'],
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
        $session = $this->resolveSession($date, $time);
        $pricesByType = collect($session['prices'])->keyBy('ticket_type');

        return $products
            ->filter(function (Product $product) use ($pricesByType): bool {
                if ($product->kind !== ProductKind::Ticket) {
                    return true;
                }

                return filled($product->ticket_type) && $pricesByType->has((string) $product->ticket_type);
            })
            ->map(function (Product $product) use ($pricesByType): Product {
                if ($product->kind !== ProductKind::Ticket || blank($product->ticket_type)) {
                    return $product;
                }

                $price = $pricesByType->get((string) $product->ticket_type);

                if (! is_array($price)) {
                    return $product;
                }

                $product->price = (int) $price['price'];
                $product->weekend_price = (int) $price['price'];

                return $product;
            })
            ->values();
    }

    private function remember(string $key, callable $callback): mixed
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        return $this->memo[$key] = $callback();
    }

    private function clockFromIso(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
