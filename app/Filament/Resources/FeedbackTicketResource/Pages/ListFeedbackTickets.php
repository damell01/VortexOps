<?php

namespace App\Filament\Resources\FeedbackTicketResource\Pages;

use App\Filament\Resources\FeedbackTicketResource;
use Filament\Resources\Pages\ListRecords;

class ListFeedbackTickets extends ListRecords
{
    protected static string $resource = FeedbackTicketResource::class;

    public function getSubheading(): ?string
    {
        return 'Bug reports and feature requests submitted from inside the app.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
