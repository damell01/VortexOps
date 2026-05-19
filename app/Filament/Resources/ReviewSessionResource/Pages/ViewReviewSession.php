<?php

namespace App\Filament\Resources\ReviewSessionResource\Pages;

use App\Filament\Resources\ReviewSessionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReviewSession extends ViewRecord
{
    protected static string $resource = ReviewSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
