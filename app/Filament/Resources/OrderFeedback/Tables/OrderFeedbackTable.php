<?php

namespace App\Filament\Resources\OrderFeedback\Tables;

use App\Enums\FeedbackRating;
use App\Enums\FeedbackTag;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderFeedbackTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('order'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order.code')
                    ->label('Заказ')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rating')
                    ->label('Оценка')
                    ->badge()
                    ->formatStateUsing(fn (FeedbackRating $state): string => $state->label())
                    ->color(fn (FeedbackRating $state): string => match ($state) {
                        FeedbackRating::Positive => 'success',
                        FeedbackRating::Negative => 'danger',
                    }),
                TextColumn::make('likes')
                    ->label('Плюсы')
                    ->formatStateUsing(fn (?array $state): string => self::formatTags($state))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('dislikes')
                    ->label('Минусы')
                    ->formatStateUsing(fn (?array $state): string => self::formatTags($state))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('comment')
                    ->label('Комментарий')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('rating')
                    ->label('Оценка')
                    ->options(collect(FeedbackRating::cases())->mapWithKeys(
                        fn (FeedbackRating $rating): array => [$rating->value => $rating->label()]
                    )->all()),
            ])
            ->recordActions([
                EditAction::make()->label('Просмотр'),
                DeleteAction::make()->label('Удалить'),
            ])
            ->toolbarActions([
                //
            ]);
    }

    /**
     * @param  list<string>|null  $state
     */
    private static function formatTags(?array $state): string
    {
        if ($state === null || $state === []) {
            return '—';
        }

        return collect($state)
            ->map(fn (string $value): string => FeedbackTag::tryFrom($value)?->label() ?? $value)
            ->implode(', ');
    }
}
