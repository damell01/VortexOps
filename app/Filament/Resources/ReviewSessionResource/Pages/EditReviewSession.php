<?php

namespace App\Filament\Resources\ReviewSessionResource\Pages;

use App\Filament\Resources\ReviewSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReviewSession extends EditRecord
{
    protected static string $resource = ReviewSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
