<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\RedirectsToVenueIndex;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Support\VenueSection;
use App\Models\MenuCategory;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use RedirectsToVenueIndex;

    protected static string $resource = ProductResource::class;

    public ?string $venueTab = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->venueTab = VenueSection::fromRequest()
            ?? VenueSection::fromRecord($this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (VenueSection::isMealsContext($this->getRecord(), $this->venueTab)) {
            $category = MenuCategory::query()->find($data['menu_category_id'] ?? null);
            $data['food_section'] = $category?->name;

            return $data;
        }

        return ProductForm::normalizeFoodSection($data);
    }
}
