<?php

namespace App\Filament\Auth;

use App\Support\Buddy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/**
 * Filament's profile page plus the dashboard buddy. Kept outside
 * app/Filament/Pages so page discovery does not register it a second time.
 */
class EditProfile extends \Filament\Auth\Pages\EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                Select::make('buddy')
                    ->label('Dashboard buddy')
                    ->helperText('The Pokémon that peeks in on your dashboard. Random picks a different one each day.')
                    ->options(Buddy::options())
                    ->placeholder('Not chosen yet (Pikachu)')
                    ->visible(fn () => (bool) auth()->user()?->streamer),
                DatePicker::make('birthday')
                    ->label('Birthday')
                    ->helperText('Optional. A Pokémon in a party hat visits you all day on your birthday. Only the month and day are shown.')
                    ->native(false)
                    ->displayFormat('F j')
                    ->maxDate(now()),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
