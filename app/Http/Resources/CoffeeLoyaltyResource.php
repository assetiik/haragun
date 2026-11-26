<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{paid_stamps: int, total_slots: int, stamps_until_reward: int, reward_available: bool} $resource
 */
class CoffeeLoyaltyResource extends JsonResource
{
    /**
     * @return array{paid_stamps: int, total_slots: int, stamps_until_reward: int, reward_available: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'paid_stamps' => (int) $this->resource['paid_stamps'],
            'total_slots' => (int) $this->resource['total_slots'],
            'stamps_until_reward' => (int) $this->resource['stamps_until_reward'],
            'reward_available' => (bool) $this->resource['reward_available'],
        ];
    }
}
