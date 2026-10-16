<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\EstablishmentType;
use App\Enums\MenuCatalog;
use App\Enums\ProductKind;
use App\Filament\Concerns\HasHaragunAndCafeTabs;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    use HasHaragunAndCafeTabs;

    protected static string $resource = ProductResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'haragun' => Tab::make('Haragun')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereHas(
                        'establishment',
                        fn (Builder $venueQuery): Builder => $venueQuery->where('type', EstablishmentType::IceArena),
                    )
                    ->where(fn (Builder $products): Builder => $products
                        ->whereDoesntHave('menuCategory')
                        ->orWhereHas(
                            'menuCategory',
                            fn (Builder $categoryQuery): Builder => $categoryQuery
                                ->where('type', '!=', EstablishmentType::Coffee)
                                ->whereNull('catalog'),
                        ))
                    ->where('kind', '!=', ProductKind::RentalSkates)),
            'haragun-skates' => Tab::make('Коньки')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereHas(
                        'establishment',
                        fn (Builder $venueQuery): Builder => $venueQuery->where('type', EstablishmentType::IceArena),
                    )
                    ->where('kind', ProductKind::RentalSkates)),
            'haragun-cafe' => Tab::make('Кофейня')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('brand_id')
                    ->whereHas(
                        'brand',
                        fn (Builder $brandQuery): Builder => $brandQuery->where('type', EstablishmentType::IceArena),
                    )
                    ->whereHas(
                        'menuCategory',
                        fn (Builder $categoryQuery): Builder => $categoryQuery->where(
                            fn (Builder $match): Builder => $match
                                ->where('type', EstablishmentType::Coffee)
                                ->orWhere('catalog', MenuCatalog::Drinks),
                        ),
                    )),
            'haragun-meals' => Tab::make('Еда')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('brand_id')
                    ->whereHas(
                        'brand',
                        fn (Builder $brandQuery): Builder => $brandQuery->where('type', EstablishmentType::IceArena),
                    )
                    ->whereHas(
                        'menuCategory',
                        fn (Builder $categoryQuery): Builder => $categoryQuery->where('catalog', MenuCatalog::Snacks),
                    )),
            'cafes' => Tab::make('Кофейни')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->whereNotNull('brand_id')
                    ->whereHas(
                        'brand',
                        fn (Builder $brandQuery): Builder => $brandQuery->where('type', EstablishmentType::Coffee),
                    )),
        ];
    }

    protected function haragunTitle(): string
    {
        return 'Меню';
    }

    protected function haragunCafeTitle(): string
    {
        return 'Напитки';
    }

    protected function haragunMealsTitle(): string
    {
        return 'Еда';
    }

    protected function haragunSkatesTitle(): string
    {
        return 'Коньки';
    }

    protected function cafeTitle(): string
    {
        return 'Напитки';
    }
}
