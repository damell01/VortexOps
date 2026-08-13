<?php

namespace App\Filament\Widgets;

use App\Models\StreamerLogEntry;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

/**
 * Surfaces the show's streamer log on the show view page.
 *
 * Admins reviewing a show previously had to know the log existed and navigate
 * to /admin/streamer-logs/{id} separately; the submitted numbers are the whole
 * point of the review, so they belong on the show itself.
 */
class ShowStreamerLogWidget extends Widget
{
    protected static ?int $sort = 10;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.show-streamer-log';

    public ?Model $record = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() ?? false) || ($user?->isOwner() ?? false);
    }

    public function getLog(): ?StreamerLogEntry
    {
        if (! $this->record) {
            return null;
        }

        // Lazy loading is disabled outside production, so pull the relation
        // explicitly rather than relying on it already being hydrated.
        return $this->record->relationLoaded('streamerLogEntry')
            ? $this->record->getRelation('streamerLogEntry')
            : $this->record->streamerLogEntry()->with('streamer')->first();
    }
}
