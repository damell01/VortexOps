<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Jobs\NotifyShowReady;
use App\Jobs\ParseShowTitle;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShows extends ListRecords
{
    protected static string $resource = ShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
