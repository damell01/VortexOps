<?php

namespace App\Filament\Resources\ReceivingSessionResource\Pages;

use App\Filament\Resources\ReceivingSessionResource;
use App\Models\Pallet;
use App\Models\ReceivingSession;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateReceivingSession extends CreateRecord
{
    protected static string $resource = ReceivingSessionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['received_by'] = Auth::id();
        $data['status']      = ReceivingSession::STATUS_PENDING;
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return ReviewReceivingSession::getUrl(['record' => $this->getRecord()]);
    }
}
