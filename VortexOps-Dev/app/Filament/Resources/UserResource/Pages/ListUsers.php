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
        return 'Team logins. Assign a role to control what someone can see and do — fine-tune per-page access under Settings → Roles & Permissions.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
