<?php

namespace App\Filament\Resources\WhatnotChannelResource\Pages;

use App\Filament\Resources\WhatnotChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatnotChannels extends ListRecords
{
    protected static string $resource = WhatnotChannelResource::class;

    public function getSubheading(): ?string
    {
        return 'Your connected Whatnot channels — branding, scrape credentials, and status. Switch the active channel from the sidebar to scope the whole app to one.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
