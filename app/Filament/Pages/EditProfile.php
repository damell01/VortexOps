<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use DateTimeZone;

class EditProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $title = 'Edit Profile';

    protected static bool $shouldRegisterNavigation = false;

    public function getView(): string
    {
        return 'filament.pages.edit-profile';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth()->user()->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Select::make('timezone')
                    ->label('Timezone')
                    ->options($this->getTimezoneOptions())
                    ->default('UTC')
                    ->searchable(),
            ])
            ->model(auth()->user())
            ->statePath('data');
    }

    public function save(): void
    {
        auth()->user()->update($this->form->getState());

        Notification::make()
            ->title('Profile updated')
            ->success()
            ->send();
    }

    private function getTimezoneOptions(): array
    {
        $timezones = DateTimeZone::listIdentifiers();
        return array_combine($timezones, $timezones);
    }
}
