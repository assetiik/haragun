<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\User;
use App\Support\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin User
 */
class ProfileScreenResource extends JsonResource
{
    /**
     * @param  Collection<int, Order>  $recentOrders
     */
    public function __construct($resource, private Collection $recentOrders)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->resource),
            'orders' => OrderListResource::collection($this->recentOrders),
            'notifications_enabled' => (bool) $this->notifications_enabled,
            'language' => 'ru',
            'about' => SiteContent::about(),
            'contacts' => SiteContent::contacts(),
        ];
    }
}
