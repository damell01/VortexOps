<?php

namespace App\Observers;

use App\Jobs\NotifyShowPendingReview;
use App\Models\Show;
use App\Models\ShowChangeLog;
use App\Models\StreamerLogEntry;
use App\Models\User;
use Filament\Notifications\Notification;

class ShowObserver
{
    private const TRACKED_WHATNOT_FIELDS = [
        'title', 'show_date', 'start_time', 'completed_earnings', 'avg_order_value',
        'giveaway_spend', 'giveaways_count', 'buyers_count', 'first_time_buyers',
        'returning_buyers', 'shares_count', 'show_duration', 'max_concurrent_viewers',
        'total_views', 'avg_order_rating',
    ];

    private const APPROVED_ALERT_FIELDS = [
        'gross_revenue', 'whatnot_net', 'units_sold', 'completed_earnings', 'avg_order_value',
        'giveaway_spend', 'giveaways_count', 'buyers_count', 'first_time_buyers',
        'returning_buyers', 'shares_count', 'show_duration', 'max_concurrent_viewers',
        'total_views', 'avg_order_rating',
    ];

    public function updating(Show $show): void
    {
        if ($show->import_source !== 'auto_whatnot') return;

        foreach (self::TRACKED_WHATNOT_FIELDS as $field) {
            if (! $show->isDirty($field)) continue;

            $old = $show->getOriginal($field);
            $new = $show->getAttribute($field);
            $normalize = static fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
            $old = $normalize($old);
            $new = $normalize($new);

            if ((string) $old !== (string) $new) {
                ShowChangeLog::logChange($show, $field, $old, $new, 'whatnot_import');
            }
        }
    }

    public function created(Show $show): void
    {
        $this->detectImportedShowStreamer($show, true);
    }

    public function updated(Show $show): void
    {
        if ($show->wasChanged('status') && $show->status === 'pending_review') {
            NotifyShowPendingReview::dispatch($show->id);
        }

        if ($show->wasChanged('title') || $show->ai_streamer_suggestion === null) {
            $this->detectImportedShowStreamer($show, $show->wasChanged('title'));
        }

        if ($show->wasChanged('status') && $show->status === 'reconciled') {
            $primaryStreamer = $show->primaryStreamer();
            if ($primaryStreamer && ! StreamerLogEntry::where('show_id', $show->id)->exists()) {
                StreamerLogEntry::create([
                    'show_id' => $show->id,
                    'streamer_id' => $primaryStreamer->id,
                    'status' => 'pending',
                    'gross_revenue' => $show->gross_revenue,
                ]);
            }
        }

        $this->notifyApprovedShowAnalyticsChange($show);
    }

    private function notifyApprovedShowAnalyticsChange(Show $show): void
    {
        if ($show->import_source !== 'auto_whatnot') return;

        $changed = collect(self::APPROVED_ALERT_FIELDS)
            ->filter(fn (string $field) => $show->wasChanged($field))
            ->values();

        if ($changed->isEmpty()) return;

        $log = StreamerLogEntry::where('show_id', $show->id)->first();
        if (! $log || $log->status !== 'admin_approved') return;

        $admins = User::query()->get()->filter(fn (User $user) => $user->isAdmin() || $user->isOwner());
        if ($admins->isEmpty()) return;

        $labels = $changed->map(fn (string $field) => ucwords(str_replace('_', ' ', $field)))->take(5)->join(', ');

        Notification::make()
            ->title('Whatnot data changed after approval')
            ->body("{$show->title}: {$labels} changed after approval. Review Show Activity if the change affects operations or payout reporting.")
            ->warning()
            ->sendToDatabase($admins);
    }

    private function detectImportedShowStreamer(Show $show, bool $forceAttempt = false): void
    {
        if ($show->import_source !== 'auto_whatnot' || blank($show->title)) return;
        if ($show->streamers()->exists()) return;
        if (! $forceAttempt && is_array($show->ai_streamer_suggestion)) return;

        $suggestions = $show->detectStreamers();
        if ($suggestions === []) {
            $show->updateQuietly(['ai_streamer_suggestion' => []]);
        }
    }
}
