<?php

namespace App\Filament\Resources\Establishments\Pages;

use App\Filament\Concerns\FillsVenueTypeFromRequest;
use App\Filament\Resources\Establishments\EstablishmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEstablishment extends CreateRecord
{
    use FillsVenueTypeFromRequest;

    protected static string $resource = EstablishmentResource::class;
}
