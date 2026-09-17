<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markPreparing')
                ->label('В работу')
                ->icon('heroicon-o-fire')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === OrderStatus::Accepted)
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => OrderStatus::Preparing,
                        'preparing_at' => now(),
                    ]);

                    Notification::make()->title('Заказ готовится')->success()->send();
                }),
            Action::make('markReady')
                ->label('Готов к выдаче')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => in_array($this->record->status, [
                    OrderStatus::Accepted,
                    OrderStatus::Preparing,
                ], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => OrderStatus::Ready,
                        'preparing_at' => $this->record->preparing_at ?? now(),
                        'ready_at' => now(),
                    ]);

                    Notification::make()->title('Заказ готов')->success()->send();
                }),
            Action::make('markCompleted')
                ->label('Выдан')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === OrderStatus::Ready)
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => OrderStatus::Completed,
                        'completed_at' => now(),
                    ]);

                    Notification::make()->title('Заказ выдан')->success()->send();
                }),
            EditAction::make(),
        ];
    }
}
