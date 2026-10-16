<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Enums\EstablishmentType;
use App\Filament\Concerns\HasHaragunAndCafeTabs;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMenuCategories extends ListRecords
{
    use HasHaragunAndCafeTabs;

    protected static string $resource = MenuCategoryResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'haragun' => Tab::make('Haragun')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('type', EstablishmentType::IceArena)
                    ->whereNull('catalog')),
            'haragun-food' => Tab::make('Блюда')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('type', EstablishmentType::IceArena)
                    ->whereNotNull('catalog')),
            'cafes' => Tab::make('Кофейни')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('type', EstablishmentType::Coffee)),
        ];
    }

    protected function haragunTitle(): string
    {
        return 'Категории';
    }

    protected function haragunFoodTitle(): string
    {
        return 'Категории блюд';
    }

    protected function cafeTitle(): string
    {
        return 'Категории';
    }
}
