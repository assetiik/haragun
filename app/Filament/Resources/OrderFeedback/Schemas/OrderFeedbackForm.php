<?php

namespace App\Filament\Resources\OrderFeedback\Schemas;

use App\Enums\FeedbackRating;
use App\Enums\FeedbackTag;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderFeedbackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->extraAttributes(['class' => 'fi-fixed-positioning-context'])
            ->components([
                Section::make('Отзыв')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('order_id')
                            ->label('Заказ')
                            ->relationship('order', 'code')
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        Select::make('rating')
                            ->label('Оценка')
                            ->options(collect(FeedbackRating::cases())->mapWithKeys(
                                fn (FeedbackRating $rating): array => [$rating->value => $rating->label()]
                            )->all())
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        CheckboxList::make('likes')
                            ->label('Плюсы')
                            ->options(collect(FeedbackTag::likeOptions())->mapWithKeys(
                                fn (FeedbackTag $tag): array => [$tag->value => $tag->label()]
                            )->all())
                            ->disabled()
                            ->dehydrated(false)
                            ->columns(2)
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        CheckboxList::make('dislikes')
                            ->label('Минусы')
                            ->options(collect(FeedbackTag::dislikeOptions())->mapWithKeys(
                                fn (FeedbackTag $tag): array => [$tag->value => $tag->label()]
                            )->all())
                            ->disabled()
                            ->dehydrated(false)
                            ->columns(2)
                            ->columnSpan([
                                'md' => 6,
                            ]),
                        Textarea::make('comment')
                            ->label('Комментарий')
                            ->rows(4)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
            ]);
    }
}
