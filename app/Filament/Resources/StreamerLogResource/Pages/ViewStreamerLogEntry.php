<?php

namespace App\Filament\Resources\StreamerLogResource\Pages;

use App\Filament\Resources\StreamerLogResource;
use App\Models\StreamerLogEntry;
use Filament\Resources\Pages\ViewRecord;

class ViewStreamerLogEntry extends ViewRecord
{
    protected static string $resource = StreamerLogResource::class;

    public function getView(): string
    {
        return 'filament.resources.streamer-log-resource.pages.view-streamer-log-entry';
    }

    protected function resolveRecord(int|string $key): StreamerLogEntry
    {
        $record = StreamerLogEntry::with([
            'show.orders.inventoryItem',
            'show.shipments',
            'streamer',
        ])->findOrFail($key);

        // Verify user has access to this record
        $user = auth()->user();
        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            // Streamers can only access their own logs
            $streamerId = $user->streamer?->id;
            if ($record->streamer_id !== $streamerId) {
                abort(403);
            }
        }

        return $record;
    }
}
