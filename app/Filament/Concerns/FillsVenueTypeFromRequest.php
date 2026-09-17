<?php

namespace App\Filament\Concerns;

use App\Filament\Support\VenueSection;

trait FillsVenueTypeFromRequest
{
    public ?string $venueTab = null;

    public function mount(): void
    {
        parent::mount();

        $this->venueTab = VenueSection::fromRequest();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = VenueSection::type($this->venueTab);

        if ($type !== null) {
            $data['type'] = $type->value;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return VenueSection::withTab(parent::getRedirectUrl(), $this->venueTab);
    }
}
