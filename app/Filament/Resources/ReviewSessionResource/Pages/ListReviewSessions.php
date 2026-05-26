<?php

namespace App\Filament\Resources\ReviewSessionResource\Pages;

use App\Filament\Resources\ReviewSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReviewSessions extends ListRecords
{
    protected static string $resource = ReviewSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
