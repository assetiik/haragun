<?php

namespace App\Filament\Concerns;

use App\Filament\Support\VenueSection;

trait RedirectsToVenueIndex
{
    protected function getRedirectUrl(): ?string
    {
        $url = parent::getRedirectUrl();

        if (! filled($url)) {
            return $url;
        }

        $tab = VenueSection::fromRequest() ?? VenueSection::fromRecord($this->getRecord());

        return VenueSection::withTab($url, $tab);
    }
}
