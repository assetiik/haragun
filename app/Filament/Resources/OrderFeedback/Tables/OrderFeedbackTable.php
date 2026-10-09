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
                    ->formatStateUsing(fn (mixed $state): string => self::formatTags($state))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('dislikes')
                    ->label('Минусы')
                    ->formatStateUsing(fn (mixed $state): string => self::formatTags($state))
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
     * Filament may pass the full array or each tag string when listing array state.
     */
    private static function formatTags(mixed $state): string
    {
        if ($state === null || $state === [] || $state === '') {
            return '—';
        }

        $tags = is_array($state) ? $state : [$state];

        return collect($tags)
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->map(fn (string $value): string => FeedbackTag::tryFrom($value)?->label() ?? $value)
            ->implode(', ');
    }
}
