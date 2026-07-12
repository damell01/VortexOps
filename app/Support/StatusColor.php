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
 * The buckets read by operational meaning to whoever's looking:
 *   gray    — not started / inactive / archived (nothing to do)
 *   warning — needs attention or in progress (someone should look)
 *   info    — acknowledged and advancing, awaiting the next step
 *   success — done well / terminal-good
 *   danger  — a problem: rejected, refunded, cancelled, failed
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

        // warning — needs attention / in progress
        'pending'          => 'warning',
        'pending_review'   => 'warning',
        'pending_approval' => 'warning',
        'in_progress'      => 'warning',
        'processing'       => 'warning',
        'receiving'        => 'warning',
        'reviewing'        => 'warning',
        'opening'          => 'warning',
        'partial'          => 'warning',

        // info — acknowledged, advancing toward done
        'approved'          => 'info',
        'streamer_reviewed' => 'info',
        'finalized'         => 'info',
        'submitted_to_adp'  => 'info',
        'mapping'           => 'info',
        'received'          => 'info',
        'needs_info'        => 'info',
        'opened'            => 'info',
        'parsing'           => 'info',

        // success — terminal-good
        'completed'      => 'success',
        'processed'      => 'success',
        'reconciled'     => 'success',
        'paid'           => 'success',
        'paid_off'       => 'success',
        'resolved'       => 'success',
        'admin_approved' => 'success',
        'success'        => 'success',
        'active'         => 'success',

        // danger — a problem
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
