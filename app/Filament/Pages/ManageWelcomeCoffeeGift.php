<?php

namespace App\Filament\Pages;

use App\Enums\EstablishmentType;
use App\Enums\ProductKind;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageWelcomeCoffeeGift extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Кофейни';

    protected static ?string $navigationLabel = 'Подарок при регистрации';

    protected static ?string $title = 'Подарок при регистрации';

    protected static ?string $slug = 'welcome-coffee-gift';

    protected static ?int $navigationSort = 7;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'product_id' => Product::query()
                ->where('is_welcome_espresso', true)
                ->value('id'),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Напиток в подарок')
                    ->description('Новый гость при первой регистрации получает 1 такой напиток бесплатно. Только кофейни Haragun Express.')
                    ->schema([
                        Select::make('product_id')
                            ->label('Напиток')
                            ->placeholder('Не выбран')
                            ->helperText('Можно оставить пустым — подарок не будет выдаваться, пока не выберете напиток.')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->options(fn (): array => $this->coffeeProductOptions())
                            ->native(false),
                    ]),
            ]);
    }

    public function save(): void
    {
        $productId = $this->form->getState()['product_id'] ?? null;

        Product::query()
            ->where('is_welcome_espresso', true)
            ->when(
                filled($productId),
                fn (Builder $query): Builder => $query->whereKeyNot((int) $productId),
            )
            ->update(['is_welcome_espresso' => false]);

        if (filled($productId)) {
            Product::query()
                ->whereKey((int) $productId)
                ->update(['is_welcome_espresso' => true]);
        }

        Notification::make()
            ->title('Сохранено')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    Action::make('save')
                        ->label('Сохранить')
                        ->submit('save')
                        ->keyBindings(['mod+s']),
                ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private function coffeeProductOptions(): array
    {
        return Product::query()
            ->with(['brand', 'establishment', 'menuCategory'])
            ->where('kind', ProductKind::Food)
            ->where('is_active', true)
            ->whereHas(
                'menuCategory',
                fn (Builder $query): Builder => $query->where('type', EstablishmentType::Coffee),
            )
            ->ordered()
            ->get()
            ->mapWithKeys(function (Product $product): array {
                $owner = $product->brand?->name
                    ?? $product->establishment?->nameWithAddress()
                    ?? 'Без точки';

                return [$product->id => "{$product->name} · {$owner}"];
            })
            ->all();
    }
}
