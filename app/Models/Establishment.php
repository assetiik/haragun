<?php

namespace App\Models;

use App\Enums\EstablishmentType;
use App\Models\Concerns\HasI18n;
use App\Support\StoredUrl;
use Carbon\CarbonInterface;
use Database\Factories\EstablishmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'city_id',
    'brand_id',
    'name',
    'address',
    'phone',
    'type',
    'latitude',
    'longitude',
    'opens_at',
    'closes_at',
    'parking_is_free',
    'session_duration_minutes',
    'slot_interval_minutes',
    'ice_temperature',
    'ice_note',
    'header_color',
    'image_url',
    'images',
    'booking_days_ahead',
    'is_active',
    'sort_order',
    'i18n',
])]
class Establishment extends Model
{
    /** @use HasFactory<EstablishmentFactory> */
    use HasFactory;

    use HasI18n;

    /**
     * @return list<string>
     */
    protected function translatableAttributes(): array
    {
        return ['name', 'address'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EstablishmentType::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'parking_is_free' => 'boolean',
            'session_duration_minutes' => 'integer',
            'slot_interval_minutes' => 'integer',
            'ice_temperature' => 'integer',
            'booking_days_ahead' => 'integer',
            'images' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'i18n' => 'array',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function publicImageUrl(): ?string
    {
        return StoredUrl::public($this->image_url);
    }

    /**
     * @return list<string>
     */
    public function publicImages(): array
    {
        return collect($this->images ?? [])
            ->map(fn (mixed $path): ?string => StoredUrl::public(is_string($path) ? $path : null))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Cover first, then gallery (no duplicates).
     *
     * @return list<string>
     */
    public function carouselImages(): array
    {
        return collect([$this->publicImageUrl(), ...$this->publicImages()])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function unavailableSlots(): HasMany
    {
        return $this->hasMany(EstablishmentUnavailableSlot::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    public function isFavoritedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->relationLoaded('favoritedBy')) {
            return $this->favoritedBy->contains(fn (User $favoritedBy): bool => $favoritedBy->id === $user->id);
        }

        return $this->favoritedBy()->whereKey($user->id)->exists();
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    #[Scope]
    protected function iceArena(Builder $query): void
    {
        $query->where('type', EstablishmentType::IceArena);
    }

    #[Scope]
    protected function coffee(Builder $query): void
    {
        $query->where('type', EstablishmentType::Coffee);
    }

    public function offersCoffeeMenu(): bool
    {
        return $this->is_active && in_array($this->type, [
            EstablishmentType::Coffee,
            EstablishmentType::IceArena,
        ], true);
    }

    public function isOpenAt(?CarbonInterface $moment = null): bool
    {
        $moment ??= now();
        $current = $moment->format('H:i:s');
        $opensAt = $this->timeValue($this->opens_at);
        $closesAt = $this->timeValue($this->closes_at);

        if ($opensAt <= $closesAt) {
            return $current >= $opensAt && $current < $closesAt;
        }

        return $current >= $opensAt || $current < $closesAt;
    }

    public function statusLabel(?CarbonInterface $moment = null): string
    {
        $closesAt = Carbon::parse($this->timeValue($this->closes_at))->format('H:i');

        if ($this->isOpenAt($moment)) {
            return "Открыто до {$closesAt}";
        }

        $opensAt = Carbon::parse($this->timeValue($this->opens_at))->format('H:i');

        return "Откроется в {$opensAt}";
    }

    public function openStateLabel(?CarbonInterface $moment = null): string
    {
        return $this->isOpenAt($moment) ? 'Открыто' : 'Закрыто';
    }

    public function hoursLabel(): string
    {
        return $this->formattedTime($this->opens_at).'-'.$this->formattedTime($this->closes_at);
    }

    public function formattedPhone(): ?string
    {
        if (! is_string($this->phone) || $this->phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $this->phone);

        if (! is_string($digits) || strlen($digits) !== 11) {
            return $this->phone;
        }

        return sprintf(
            '+%s %s %s %s %s',
            $digits[0],
            substr($digits, 1, 3),
            substr($digits, 4, 3),
            substr($digits, 7, 2),
            substr($digits, 9, 2),
        );
    }

    public function iceStatusLabel(): ?string
    {
        if ($this->type !== EstablishmentType::IceArena || $this->ice_temperature === null) {
            return null;
        }

        $note = $this->ice_note ? ", {$this->ice_note}" : '';

        return "{$this->ice_temperature} °C{$note}";
    }

    public function locationMeta(?float $latitude = null, ?float $longitude = null): string
    {
        $parts = [$this->address];

        $distance = $this->distanceLabel($latitude, $longitude);
        if ($distance !== null) {
            $parts[] = "{$distance} от вас";
        }

        if ($this->parking_is_free) {
            $parts[] = 'парковка бесплатная';
        }

        return implode(' · ', $parts);
    }

    public function nameWithAddress(): string
    {
        if (blank($this->address)) {
            return $this->name;
        }

        return $this->name.' · '.$this->address;
    }

    public function distanceKm(?float $latitude, ?float $longitude): ?float
    {
        if ($latitude === null || $longitude === null || $this->latitude === null || $this->longitude === null) {
            return null;
        }

        $earthRadius = 6371;
        $latFrom = deg2rad($latitude);
        $lonFrom = deg2rad($longitude);
        $latTo = deg2rad($this->latitude);
        $lonTo = deg2rad($this->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            (sin($latDelta / 2) ** 2)
            + cos($latFrom) * cos($latTo) * (sin($lonDelta / 2) ** 2)
        ));

        return round($earthRadius * $angle, 1);
    }

    public function distanceLabel(?float $latitude, ?float $longitude): ?string
    {
        $distance = $this->distanceKm($latitude, $longitude);

        if ($distance === null) {
            return null;
        }

        return str_replace('.', ',', number_format($distance, 1, '.', '')).' км';
    }

    public function formattedTime(mixed $value): string
    {
        return Carbon::parse($this->timeValue($value))->format('H:i');
    }

    private function timeValue(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i:s');
        }

        return (string) $value;
    }
}
