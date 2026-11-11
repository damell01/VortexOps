<?php

namespace App\Support;

/**
 * One source of truth for status → badge colour across every resource.
 *
 * Before this, each table inlined its own `match ($state)`, so the same word
 * wore different colours in different places — "pending" was grey on the
 * streamer log but amber everywhere else, "inactive" was red here and grey
 * there. Route a status column's colour through {@see self::for()} and every
 * badge in the app speaks the same visual language.
 *
 * The buckets read the same everywhere in the app:
 *   success (green) — done
 *   info    (blue)  — in progress, moving on its own
 *   warning (amber) — needs attention: someone should look or act
 *   danger  (red)   — a problem: rejected, refunded, cancelled, failed
 *   gray            — not started / inactive / archived (nothing to do)
 *
 * A genuinely domain-specific status (e.g. an "active" loan means money is
 * still owed, so it's a warning not a success) should keep its own inline
 * mapping rather than bend this shared meaning.
 */
class StatusColor
{
    /** @var array<string, string> status token => Filament colour */
    private const MAP = [
        // gray — neutral / not started / archived
        'draft'            => 'gray',
        'inactive'         => 'gray',
        'expected'         => 'gray',
        'closed'           => 'gray',

        // warning (amber) — needs attention
        'pending'          => 'warning',
        'pending_review'   => 'warning',
        'pending_approval' => 'warning',
        'reviewing'        => 'warning',
        'partial'          => 'warning',
        'needs_info'       => 'warning',
        'paused'           => 'warning',
        'on_hold'          => 'warning',
        'hold'             => 'warning',

        // info (blue) — in progress
        'in_progress'       => 'info',
        'processing'        => 'info',
        'receiving'         => 'info',
        'opening'           => 'info',
        'packing'           => 'info',
        'approved'          => 'info',
        'streamer_reviewed' => 'info',
        'finalized'         => 'info',
        'submitted_to_adp'  => 'info',
        'mapping'           => 'info',
        'received'          => 'info',
        'shipped'           => 'info',
        'in_transit'        => 'info',
        'opened'            => 'info',
        'parsing'           => 'info',

        // success (green) — done
        'delivered'      => 'success',
        'signed_off'     => 'success',
        'completed'      => 'success',
        'processed'      => 'success',
        'reconciled'     => 'success',
        'paid'           => 'success',
        'paid_off'       => 'success',
        'resolved'       => 'success',
        'admin_approved' => 'success',
        'success'        => 'success',
        'active'         => 'success',

        // danger (red) — a problem
        'rejected'  => 'danger',
        'refunded'  => 'danger',
        'cancelled' => 'danger',
        'failed'    => 'danger',
        'open'      => 'danger',
        'overdue'   => 'danger',
    ];

    /**
     * The badge colour for a status token. Unknown or empty statuses fall back
     * to grey. Matching is case-insensitive so imported values ("Completed")
     * line up with internal tokens ("completed").
     */
    public static function for(string|\BackedEnum|null $status): string
    {
        if ($status instanceof \BackedEnum) {
            $status = (string) $status->value;
        }

        $key = strtolower(trim((string) $status));

        return self::MAP[$key] ?? 'gray';
    }
}
