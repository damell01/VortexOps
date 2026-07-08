<?php

namespace App\Observers;

use App\Jobs\NotifyShowPendingReview;
use App\Models\Show;
use App\Models\StreamerLogEntry;

class ShowObserver
{
    public function updated(Show $show): void
    {
        if ($show->isDirty('status') && $show->status === 'pending_review') {
            NotifyShowPendingReview::dispatch($show->id);
        }

        // When a show transitions to 'reconciled', auto-create a blank StreamerLogEntry
        // for the primary streamer if one does not already exist.
        if (
            $show->isDirty('status')
            && $show->status === 'reconciled'
        ) {
            $primaryStreamer = $show->primaryStreamer();

            if ($primaryStreamer && ! StreamerLogEntry::where('show_id', $show->id)->exists()) {
                StreamerLogEntry::create([
                    'show_id'       => $show->id,
                    'streamer_id'   => $primaryStreamer->id,
                    'status'        => 'pending',
                    'gross_revenue' => $show->gross_revenue,
                ]);
            }
        }
    }
}
