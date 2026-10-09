<?php

namespace App\Filament\Pages;

use App\Filament\Support\LocalizedFields;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageSiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    protected static ?string $navigationLabel = 'О приложении и контакты';

    protected static ?string $title = 'О приложении и контакты';

    protected static ?int $navigationSort = 6;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(SiteSetting::current()->only([
            'about_title',
            'about_version',
            'about_text',
            'contacts_title',
            'contacts_phone',
            'contacts_phone_raw',
            'contacts_email',
            'contacts_address',
            'contacts_hours',
            'i18n',
        ]));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->operation('edit')
            ->model(SiteSetting::class)
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('О приложении')
                    ->schema([
                        TextInput::make('about_title')
                            ->label('Заголовок · RU')
                            ->required()
                            ->maxLength(100),
                        ...LocalizedFields::text('about_title', 'Заголовок', 100),
                        TextInput::make('about_version')
                            ->label('Версия')
                            ->maxLength(50),
                        Textarea::make('about_text')
                            ->label('Текст · RU')
                            ->rows(5)
                            ->columnSpanFull(),
                        ...LocalizedFields::textarea('about_text', 'Текст'),
                    ])
                    ->columns(2),
                Section::make('Контакты')
                    ->schema([
                        TextInput::make('contacts_title')
                            ->label('Заголовок · RU')
                            ->required()
                            ->maxLength(100),
                        ...LocalizedFields::text('contacts_title', 'Заголовок', 100),
                        TextInput::make('contacts_phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('contacts_phone_raw')
                            ->label('Телефон (только цифры)')
                            ->maxLength(20),
                        TextInput::make('contacts_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(120),
                        TextInput::make('contacts_address')
                            ->label('Адрес · RU')
                            ->maxLength(255),
                        ...LocalizedFields::text('contacts_address', 'Адрес'),
                        TextInput::make('contacts_hours')
                            ->label('Часы работы · RU')
                            ->maxLength(120),
                        ...LocalizedFields::text('contacts_hours', 'Часы работы', 120),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        SiteSetting::current()->update($this->form->getState());

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
}
