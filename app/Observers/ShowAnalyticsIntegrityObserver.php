<?php

namespace App\Observers;

use App\Models\Show;
use RuntimeException;

class ShowAnalyticsIntegrityObserver
{
    /**
     * Guard every show-import path, not just one command.
     *
     * The Whatnot analytics page is a React SPA. During navigation its URL can
     * already contain the requested live_id while the cards still contain the
     * previous show's numbers. That produced identical gross/net/completed/AOV
     * signatures on different show UUIDs. If an incoming analytics write exactly
     * matches another UUID across the core metrics, refuse it rather than
     * corrupting another show.
     */
    public function saving(Show $show): void
    {
        $watched = [
            'gross_revenue',
            'whatnot_net',
            'completed_earnings',
            'avg_order_value',
            'units_sold',
            'buyers_count',
        ];

        if (! collect($watched)->contains(fn (string $field) => $show->isDirty($field))) {
            return;
        }

        if (! $show->whatnot_show_id || $show->gross_revenue === null || (float) $show->gross_revenue <= 0) {
            return;
        }

        $present = array_values(array_filter($watched, fn (string $field) => $show->getAttribute($field) !== null));
        if (count($present) < 4) {
            return;
        }

        $query = Show::query()
            ->where('id', '!=', $show->id ?? 0)
            ->whereNotNull('whatnot_show_id')
            ->where('whatnot_show_id', '!=', $show->whatnot_show_id);

        foreach ($present as $field) {
            $query->where($field, $show->getAttribute($field));
        }

        $duplicate = $query->first(['id', 'whatnot_show_id', 'title', 'show_date']);
        if (! $duplicate) {
            return;
        }

        throw new RuntimeException(
            "Blocked suspicious Whatnot analytics clone: incoming metrics for {$show->whatnot_show_id} exactly match show #{$duplicate->id} ({$duplicate->whatnot_show_id})."
        );
    }
}
