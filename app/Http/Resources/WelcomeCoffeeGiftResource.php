<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{available: bool, title: string, subtitle: string}|null $resource
 */
class WelcomeCoffeeGiftResource extends JsonResource
{
    /**
     * @return array{available: bool, title: string, subtitle: string}|null
     */
    public function toArray(Request $request): ?array
    {
        if ($this->resource === null) {
            return null;
        }

        return [
            'available' => (bool) $this->resource['available'],
            'title' => (string) $this->resource['title'],
            'subtitle' => (string) $this->resource['subtitle'],
        ];
    }
}
