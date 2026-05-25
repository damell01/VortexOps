<?php

namespace App\Filament\Resources\ReviewSessionResource\Pages;

use App\Filament\Resources\ReviewSessionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateReviewSession extends CreateRecord
{
    protected static string $resource = ReviewSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }
}
