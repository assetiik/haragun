<?php

namespace App\Filament\Concerns;

use App\Enums\EstablishmentType;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait HasHaragunAndCafeTabs
{
    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'haragun' => Tab::make('Haragun')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->constrainToVenueType($query, EstablishmentType::IceArena)),
            'cafes' => Tab::make('Кофейни')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->constrainToVenueType($query, EstablishmentType::Coffee)),
        ];
    }

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()->hidden();
    }

    public function getTitle(): string|Htmlable
    {
        return match ($this->activeTab) {
            'haragun' => $this->haragunTitle(),
            'haragun-food' => $this->haragunFoodTitle(),
            'haragun-cafe' => $this->haragunCafeTitle(),
            'haragun-skates' => $this->haragunSkatesTitle(),
            'cafes' => $this->cafeTitle(),
            default => parent::getTitle(),
        };
    }

    protected function haragunTitle(): string
    {
        return (string) parent::getTitle();
    }

    protected function haragunFoodTitle(): string
    {
        return $this->haragunTitle();
    }

    protected function haragunCafeTitle(): string
    {
        return $this->cafeTitle();
    }

    protected function haragunSkatesTitle(): string
    {
        return $this->haragunTitle();
    }

    protected function cafeTitle(): string
    {
        return (string) parent::getTitle();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->url(fn (): string => static::getResource()::getUrl('create').'?tab='.$this->activeTab),
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function constrainToVenueType(Builder $query, EstablishmentType $type): Builder
    {
        $relation = $this->venueTypeRelation();

        if ($relation === null) {
            return $query->where('type', $type);
        }

        return $query->whereHas(
            $relation,
            fn (Builder $venueQuery): Builder => $venueQuery->where('type', $type),
        );
    }

    protected function venueTypeRelation(): ?string
    {
        return null;
    }
}
