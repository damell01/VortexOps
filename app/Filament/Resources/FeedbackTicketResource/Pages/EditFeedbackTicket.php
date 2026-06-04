<?php

namespace App\Filament\Resources\FeedbackTicketResource\Pages;

use App\Filament\Resources\FeedbackTicketResource;
use Filament\Resources\Pages\EditRecord;

class EditFeedbackTicket extends EditRecord
{
    protected static string $resource = FeedbackTicketResource::class;

    protected function getRedirectUrl(): string
    {
        return FeedbackTicketResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Ticket updated.';
    }
}
