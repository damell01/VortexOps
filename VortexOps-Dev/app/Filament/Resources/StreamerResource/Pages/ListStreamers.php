<?php

namespace App\Filament\Resources\StreamerResource\Pages;

use App\Filament\Resources\StreamerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStreamers extends ListRecords
{
    protected static string $resource = StreamerResource::class;

    public function getSubheading(): ?string
    {
        return 'Your streamer roster — payout type and rate, channel routing rules, and the login each one is linked to.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
