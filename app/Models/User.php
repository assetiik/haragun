<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['phone', 'name', 'notifications_enabled'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notifications_enabled' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function favoriteEstablishments(): BelongsToMany
    {
        return $this->belongsToMany(Establishment::class, 'favorites')
            ->withTimestamps()
            ->orderByPivot('created_at', 'desc')
            ->orderByPivot('id', 'desc');
    }

    /**
     * Stable 4-digit guest code shown as «Гость 2352».
     */
    public function guestNumber(): int
    {
        return 1000 + (($this->id * 7919) % 9000);
    }

    public function formattedPhone(): string
    {
        if (strlen($this->phone) !== 11) {
            return $this->phone;
        }

        return sprintf(
            '+%s %s %s %s %s',
            $this->phone[0],
            substr($this->phone, 1, 3),
            substr($this->phone, 4, 3),
            substr($this->phone, 7, 2),
            substr($this->phone, 9, 2),
        );
    }

    public function initials(): string
    {
        if (blank($this->name)) {
            return '??';
        }

        $parts = preg_split('/\s+/u', trim($this->name)) ?: [];
        $letters = [];

        foreach (array_slice($parts, 0, 2) as $part) {
            $letters[] = mb_strtoupper(mb_substr($part, 0, 1));
        }

        return implode('', $letters) ?: '??';
    }
}
