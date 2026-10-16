<?php

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductKind;
use App\Filament\Concerns\FillsVenueTypeFromRequest;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Support\VenueSection;
use App\Models\MenuCategory;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use FillsVenueTypeFromRequest {
        mutateFormDataBeforeCreate as fillVenueTypeBeforeCreate;
    }

    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->fillVenueTypeBeforeCreate($data);

        if (VenueSection::isCafeContext(tab: $this->venueTab)) {
            $data['kind'] = ProductKind::Food->value;
            $data['food_section'] = null;
            $data = $this->fillPriceFromSizes($data);

            if (in_array($this->venueTab ?? VenueSection::fromRequest(), [VenueSection::Cafes, VenueSection::HaragunCafe], true)) {
                $data['establishment_id'] = null;
            }

            return $data;
        }

        if (VenueSection::isSkateContext(tab: $this->venueTab)) {
            $data['kind'] = ProductKind::RentalSkates->value;
            $data['requires_size'] = true;
            $data['food_section'] = null;

            return $data;
        }

        $data['kind'] = $this->kindFromCategory($data['menu_category_id'] ?? null);

        return ProductForm::normalizeFoodSection($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fillPriceFromSizes(array $data): array
    {
        $sizes = collect($this->form->getRawState()['sizes'] ?? [])
            ->filter(fn (mixed $size): bool => is_array($size) && filled($size['price'] ?? null));

        $default = $sizes->first(fn (array $size): bool => (bool) ($size['is_default'] ?? false))
            ?? $sizes->first();

        if (! is_array($default)) {
            $data['price'] = 0;

            return $data;
        }

        $data['price'] = (int) ($default['price'] ?? 0);

        if (filled($default['label'] ?? null)) {
            $data['meta'] = $default['label'];
        } elseif (filled($default['volume_ml'] ?? null)) {
            $data['meta'] = ((int) $default['volume_ml']).' мл';
        }

        return $data;
    }

    private function kindFromCategory(mixed $categoryId): string
    {
        $category = MenuCategory::query()->find($categoryId);

        if ($category?->isFoodCourt()) {
            return ProductKind::Food->value;
        }

        return ProductKind::Ticket->value;
    }
}
