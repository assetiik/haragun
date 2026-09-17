<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Пользователь')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->tel()
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpan([
                                'md' => 4,
                            ]),
                        TextInput::make('name')
                            ->label('Имя')
                            ->maxLength(255)
                            ->columnSpan([
                                'md' => 4,
                            ]),
                        TextEntry::make('guest_number')
                            ->label('Номер гостя')
                            ->state(fn (?User $record): string => $record
                                ? 'Гость '.$record->guestNumber()
                                : '—')
                            ->columnSpan([
                                'md' => 4,
                            ]),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 12,
                    ]),
            ]);
    }
}
