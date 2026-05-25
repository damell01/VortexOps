<?php

namespace App\Filament\Resources\FeedbackTicketResource\Pages;

use App\Filament\Resources\FeedbackTicketResource;
use Filament\Resources\Pages\ListRecords;

class ListFeedbackTickets extends ListRecords
{
    protected static string $resource = FeedbackTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
