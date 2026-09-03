<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
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
                Section::make('Profile')
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
                    ]),

                Section::make('Notification preferences')
                    ->description('Choose how VortexOps can notify you. Admin recipient rules still decide which events are sent to you.')
                    ->schema([
                        Toggle::make('notifications_enabled')
                            ->label('Notifications')
                            ->helperText('Turn this off to pause all VortexOps notifications for your account.')
                            ->default(true)
                            ->live(),

                        Toggle::make('notification_in_app_enabled')
                            ->label('In-app notifications')
                            ->helperText('Show notifications in the VortexOps bell and notification center.')
                            ->default(true)
                            ->disabled(fn ($get): bool => ! (bool) $get('notifications_enabled')),

                        Toggle::make('notification_email_enabled')
                            ->label('Email notifications')
                            ->helperText('Receive email for notification types that support email when email delivery is enabled by an admin.')
                            ->default(true)
                            ->disabled(fn ($get): bool => ! (bool) $get('notifications_enabled')),
                    ])
                    ->columns(1),
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
