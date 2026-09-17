<?php

namespace App\Filament\Resources\OrderFeedback\Pages;

use App\Filament\Resources\OrderFeedback\OrderFeedbackResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrderFeedback extends EditRecord
{
    protected static string $resource = OrderFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
