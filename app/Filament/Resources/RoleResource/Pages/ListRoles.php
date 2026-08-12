<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New Role')];
    }

    public function getSubheading(): ?string
    {
        return 'Admin — sees and manages everything. Streamer — only their own shows and payouts. '
            . 'Fulfillment — only shows assigned to them. Fulfillment Admin — every channel\'s fulfillment work. '
            . 'Need something in between? Click "New Role" to build a custom one — pick which pages it can see '
            . '(and whether it can edit or just view) and which Spatie permissions it grants.';
    }
}
