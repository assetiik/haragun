<?php

namespace App\Filament\Resources\LocalizationStrings\Pages;

use App\Filament\Resources\LocalizationStrings\LocalizationStringResource;
use App\Models\LocalizationString;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLocalizationStrings extends ListRecords
{
    protected static string $resource = LocalizationStringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Добавить'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('Все'),
        ];

        $sections = LocalizationString::query()
            ->select('section')
            ->distinct()
            ->orderBy('section')
            ->pluck('section');

        foreach ($sections as $section) {
            $tabs[$section] = Tab::make($section)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('section', $section));
        }

        return $tabs;
    }
}
