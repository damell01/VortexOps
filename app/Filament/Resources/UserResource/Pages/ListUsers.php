<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getSubheading(): ?string
    {
        return 'Manage team access, roles, and linked streamer profiles.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add User')->icon('heroicon-o-plus')->color('primary')];
    }
}
