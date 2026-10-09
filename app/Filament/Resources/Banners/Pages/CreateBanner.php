<?php

namespace App\Filament\Resources\Banners\Pages;

use App\Enums\BannerPlacement;
use App\Filament\Resources\Banners\BannerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBanner extends CreateRecord
{
    protected static string $resource = BannerResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['placement'] = BannerPlacement::Hero->value;

        return $data;
    }
}
