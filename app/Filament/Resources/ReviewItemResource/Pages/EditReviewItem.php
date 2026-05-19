<?php

namespace App\Filament\Resources\ReviewItemResource\Pages;

use App\Filament\Resources\ReviewItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReviewItem extends EditRecord
{
    protected static string $resource = ReviewItemResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
