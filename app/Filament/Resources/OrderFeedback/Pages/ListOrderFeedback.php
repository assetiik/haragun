<?php

namespace App\Filament\Resources\OrderFeedback\Pages;

use App\Filament\Resources\OrderFeedback\OrderFeedbackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrderFeedback extends ListRecords
{
    protected static string $resource = OrderFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
