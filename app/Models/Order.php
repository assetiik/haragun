<?php

namespace App\Models;

use App\Enums\EstablishmentType;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Observers\OrderObserver;
use App\Support\Money;
use App\Support\StoredUrl;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy([OrderObserver::class])]
#[Fillable([
    'user_id',
    'establishment_id',
    'type',
    'status',
    'number',
    'guest_number',
    'code',
    'session_date',
    'session_time',
    'session_ends_at',
    'subtotal',
    'discount_total',
    'total',
    'items_count',
    'summary_title',
    'summary_short',
    'payment_label',
    'points_to_earn',
    'prep_minutes',
    'paid_at',
    'accepted_at',
    'preparing_at',
    'ready_at',
    'completed_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::retrieved(function (Order $order): void {
            $order->promoteToPreparingIfDue();
        });

        static::updating(function (Order $order): void {
            if (! $order->isDirty('status') || $order->status !== OrderStatus::Ready) {
                return;
            }

            $order->ready_at ??= now();
            $order->preparing_at ??= now();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'session_date' => 'date',
            'number' => 'integer',
            'guest_number' => 'integer',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'total' => 'integer',
            'items_count' => 'integer',
            'points_to_earn' => 'integer',
            'prep_minutes' => 'integer',
            'paid_at' => 'datetime',
            'accepted_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function establishment(): BelongsTo
    {
        return $this->belongsTo(Establishment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(OrderFeedback::class);
    }

    public function guestLabel(): string
    {
        return 'Гость '.($this->guest_number ?? $this->number);
    }

    public const PREPARING_AFTER_SECONDS = 3;

    public function promoteToPreparingIfDue(): void
    {
        if ($this->type !== OrderType::Venue || $this->status !== OrderStatus::Accepted) {
            return;
        }

        $acceptedAt = $this->accepted_at ?? $this->created_at;

        if ($acceptedAt === null || $acceptedAt->gt(now()->subSeconds(self::PREPARING_AFTER_SECONDS))) {
            return;
        }

        $this->update([
            'status' => OrderStatus::Preparing,
            'preparing_at' => now(),
        ]);
    }

    public function markReady(): void
    {
        if ($this->type !== OrderType::Venue || $this->status === OrderStatus::Ready) {
            return;
        }

        $this->update([
            'status' => OrderStatus::Ready,
            'preparing_at' => $this->preparing_at ?? now(),
            'ready_at' => now(),
        ]);
    }

    public function hasFeedback(): bool
    {
        return $this->relationLoaded('feedback')
            ? $this->feedback !== null
            : $this->feedback()->exists();
    }

    /**
     * Coffee post-checkout / post-pickup screen key.
     */
    public function coffeeScreenKey(): ?string
    {
        if ($this->type !== OrderType::Venue) {
            return null;
        }

        if ($this->hasFeedback()) {
            return 'rated';
        }

        return match ($this->status) {
            OrderStatus::Accepted, OrderStatus::Preparing => 'accepted',
            OrderStatus::Ready => 'ready',
            OrderStatus::Completed => 'ready',
            default => null,
        };
    }

    #[Scope]
    protected function forUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    #[Scope]
    protected function ofType(Builder $query, OrderType|string $type): void
    {
        $query->where('type', $type instanceof OrderType ? $type->value : $type);
    }

    /**
     * App "Мои заказы" tabs: coffee shops vs ice arena (tickets + foodcourt/drinks).
     */
    #[Scope]
    protected function forListTab(Builder $query, OrderType|string $tab): void
    {
        $tab = $tab instanceof OrderType ? $tab : OrderType::from($tab);

        if ($tab === OrderType::Venue) {
            $query->where('type', OrderType::Venue)
                ->whereHas(
                    'establishment',
                    fn (Builder $establishment): Builder => $establishment->where('type', EstablishmentType::Coffee),
                );

            return;
        }

        $query->where(function (Builder $orders): void {
            $orders->where('type', OrderType::Tickets)
                ->orWhere(function (Builder $arenaVenue): void {
                    $arenaVenue->where('type', OrderType::Venue)
                        ->whereHas(
                            'establishment',
                            fn (Builder $establishment): Builder => $establishment->where('type', EstablishmentType::IceArena),
                        );
                });
        });
    }

    public function isActive(): bool
    {
        if ($this->type === OrderType::Tickets) {
            if ($this->status === OrderStatus::Cancelled || $this->status === OrderStatus::Completed) {
                return false;
            }

            $sessionStart = $this->sessionStartsAt();

            return $sessionStart === null || $sessionStart->copy()->addHour()->isFuture();
        }

        return $this->status->isActive($this->type);
    }

    public function totalLabel(): string
    {
        return Money::format($this->total);
    }

    public function placedAt(): CarbonInterface
    {
        return $this->paid_at ?? $this->accepted_at ?? $this->created_at;
    }

    public function dateLabel(): string
    {
        $at = $this->placedAt();

        if ($at->isToday()) {
            return 'Сегодня';
        }

        if ($at->isYesterday()) {
            return 'Вчера';
        }

        $months = [
            1 => 'января',
            2 => 'февраля',
            3 => 'марта',
            4 => 'апреля',
            5 => 'мая',
            6 => 'июня',
            7 => 'июля',
            8 => 'августа',
            9 => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
        ];

        return $at->day.' '.$months[$at->month];
    }

    public function timeLabel(): string
    {
        return $this->placedAt()->format('H:i');
    }

    public function datetimeLabel(): string
    {
        $at = $this->placedAt();

        $months = [
            1 => 'января',
            2 => 'февраля',
            3 => 'марта',
            4 => 'апреля',
            5 => 'мая',
            6 => 'июня',
            7 => 'июля',
            8 => 'августа',
            9 => 'сентября',
            10 => 'октября',
            11 => 'ноября',
            12 => 'декабря',
        ];

        return $at->day.' '.$months[$at->month].' '.$at->year.', '.$at->format('H:i');
    }

    public function metaLabel(): string
    {
        return collect([
            $this->dateLabel(),
            $this->timeLabel(),
            $this->summary_short,
        ])->filter()->implode(' · ');
    }

    /**
     * @return list<string>
     */
    public function thumbnails(): array
    {
        return $this->items
            ->where('is_discount', false)
            ->map(fn (OrderItem $item) => StoredUrl::public($item->image_url ?? $item->product?->image_url))
            ->filter()
            ->unique()
            ->take(5)
            ->values()
            ->all();
    }

    public function numberLabel(): string
    {
        return (string) $this->number;
    }

    public function titlePrefix(): string
    {
        return $this->type === OrderType::Tickets
            ? "Бронь №{$this->number}"
            : "Заказ №{$this->number}";
    }

    public function sessionStartsAt(): ?CarbonInterface
    {
        if ($this->session_date === null || $this->session_time === null) {
            return null;
        }

        $time = substr((string) $this->session_time, 0, 5);

        return Carbon::parse($this->session_date->toDateString().' '.$time);
    }

    public function sessionEndsAt(): ?CarbonInterface
    {
        if ($this->session_date === null || $this->session_ends_at === null) {
            return null;
        }

        $time = substr((string) $this->session_ends_at, 0, 5);

        return Carbon::parse($this->session_date->toDateString().' '.$time);
    }

    public function formattedSessionTime(): ?string
    {
        if ($this->session_time === null) {
            return null;
        }

        return substr((string) $this->session_time, 0, 5);
    }

    public function formattedSessionEndsAt(): ?string
    {
        if ($this->session_ends_at === null) {
            return null;
        }

        return substr((string) $this->session_ends_at, 0, 5);
    }

    public function minutesUntilReady(): ?int
    {
        if ($this->type !== OrderType::Venue || $this->status !== OrderStatus::Preparing) {
            return null;
        }

        $started = $this->preparing_at ?? $this->accepted_at ?? $this->paid_at ?? $this->created_at;
        $eta = $started->copy()->addMinutes($this->prep_minutes ?? 5);
        $left = now()->diffInMinutes($eta, false);

        return max(0, (int) ceil($left));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(): array
    {
        if ($this->type !== OrderType::Venue) {
            return [];
        }

        $current = $this->status;
        $steps = [
            [
                'key' => 'accepted',
                'title' => 'Заказ принят',
                'subtitle' => $this->accepted_at
                    ? 'оплачено картой '.($this->cardTail() ?? '')
                    : null,
                'at' => $this->accepted_at,
                'state' => $this->stepState(OrderStatus::Accepted, $current),
            ],
            [
                'key' => 'preparing',
                'title' => $current === OrderStatus::Preparing && $this->minutesUntilReady() !== null
                    ? 'Готовятся · осталось '.$this->minutesUntilReady().' минуты'
                    : 'Готовятся',
                'subtitle' => $this->preparing_at
                    ? 'Бариста взял в работу в '.$this->preparing_at->format('G:i')
                    : null,
                'at' => $this->preparing_at,
                'state' => $this->stepState(OrderStatus::Preparing, $current),
            ],
            [
                'key' => 'ready',
                'title' => 'Готов к выдаче',
                'subtitle' => 'Подойдите к стойке с кодом',
                'at' => $this->ready_at,
                'state' => $this->stepState(OrderStatus::Ready, $current),
            ],
        ];

        return array_map(function (array $step) {
            $step['time_label'] = $step['at']?->format('G:i');
            unset($step['at']);

            return $step;
        }, $steps);
    }

    public function statusBanner(): array
    {
        if ($this->type === OrderType::Venue) {
            return match ($this->status) {
                OrderStatus::Preparing => [
                    'title' => 'Готовим ваш заказ',
                    'subtitle' => 'Заберите на стойке выдачи',
                    'timer_label' => ($this->minutesUntilReady() ?? 0).' мин',
                ],
                OrderStatus::Ready => [
                    'title' => 'Заказ готов',
                    'subtitle' => 'Подойдите к стойке с кодом',
                    'timer_label' => null,
                ],
                OrderStatus::Accepted => [
                    'title' => 'Заказ принят',
                    'subtitle' => 'Скоро начнём готовить',
                    'timer_label' => null,
                ],
                default => [
                    'title' => 'Заказ завершён',
                    'subtitle' => null,
                    'timer_label' => null,
                ],
            };
        }

        $startsAt = $this->sessionStartsAt();
        $time = $this->formattedSessionTime() ?? '';

        if ($startsAt && $startsAt->isToday()) {
            $diff = now()->diff($startsAt);
            $hours = ($diff->days * 24) + $diff->h;
            $minutes = str_pad((string) $diff->i, 2, '0', STR_PAD_LEFT);

            return [
                'title' => "Сеанс сегодня в {$time}",
                'subtitle' => 'Приходите за 15 минут за коньками',
                'timer_label' => $startsAt->isFuture() ? "{$hours} ч {$minutes}" : 'сейчас',
                'timer_caption' => 'до начала',
                'badge' => "Сегодня в {$time}",
            ];
        }

        return [
            'title' => "Сеанс в {$time}",
            'subtitle' => 'Приходите за 15 минут за коньками',
            'timer_label' => null,
            'timer_caption' => null,
            'badge' => $startsAt?->translatedFormat('j M · H:i'),
        ];
    }

    public function cardTail(): ?string
    {
        if (! is_string($this->payment_label) || $this->payment_label === '') {
            return null;
        }

        if (preg_match('/(\d{4})$/', $this->payment_label, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function stepState(OrderStatus $step, OrderStatus $current): string
    {
        $order = [
            OrderStatus::Accepted->value => 1,
            OrderStatus::Preparing->value => 2,
            OrderStatus::Ready->value => 3,
            OrderStatus::Completed->value => 4,
        ];

        $stepRank = $order[$step->value] ?? 0;
        $currentRank = $order[$current->value] ?? 0;

        if ($currentRank > $stepRank) {
            return 'done';
        }

        if ($currentRank === $stepRank) {
            return 'current';
        }

        return 'pending';
    }
}
