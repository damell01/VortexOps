<?php

namespace App\Support;

use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Models\StreamerLogEntry;

/**
 * Resolves the best deep-link for a notification recipient so database
 * notifications become one-click actionable. Streamers land on their own
 * Streamer Log entry (the place they enrich a show); admins land on the show.
 * All lookups are best-effort — a failure falls back to a plain admin path so a
 * queued notification never throws.
 */
class NotificationLinks
{
    public static function forShow(int $showId, ?object $notifiable = null): string
    {
        try {
            if ($notifiable
                && method_exists($notifiable, 'isStreamer')
                && $notifiable->isStreamer()
                && ! $notifiable->isAdmin()) {
                $streamerId = $notifiable->streamer?->id;
                if ($streamerId) {
                    $entry = StreamerLogEntry::where('show_id', $showId)
                        ->where('streamer_id', $streamerId)
                        ->first();
                    if ($entry) {
                        return StreamerLogResource::getUrl('edit', ['record' => $entry]);
                    }
                }
            }

            return ShowResource::getUrl('view', ['record' => $showId]);
        } catch (\Throwable) {
            return url("/admin/shows/{$showId}");
        }
    }
}
