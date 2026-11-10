<?php

namespace App\Services;

use App\Models\WhatnotLedgerEntry;
use Illuminate\Support\Facades\DB;

class LedgerAutoMatcher
{
    /** Categories that are business-level money when no order or livestream is behind them. */
    public const HOUSE_CATEGORIES = ['marketing', 'bonus'];

    public function match(WhatnotLedgerEntry $entry): string
    {
        $result = $this->matchEntry($entry);
        // An automatically approved tip lands on its show and its draft pay, as a reviewed one does.
        if ($result === 'matched' && in_array(strtolower(trim((string) $entry->transaction_type)), ['tip', 'tips'], true)) {
            try {
                app(LedgerAttributionService::class)->syncShowTips($entry);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    private function matchEntry(WhatnotLedgerEntry $entry): string
    {
        return DB::transaction(function () use ($entry) {
            $existing = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->lockForUpdate()->first();
            // Never overwrite a human decision, even a pending or rejected one.
            if ($existing && $existing->submitted_by !== null) return 'manual';

            $service = app(LedgerAttributionService::class);
            // An automatic match must never reassign a previously approved recipient
            // merely because a show's primary streamer or business later changed.
            $show = $service->suggestedShow($entry);
            if (! $show) {
                // Business-level money (marketing, bonuses) with no order and no
                // livestream behind it belongs to the house: file and approve it
                // automatically. Anything tied to an order or stream stays out.
                $raw = is_array($entry->raw_data) ? $entry->raw_data : [];
                $hasLivestream = collect(['live_id', 'whatnot_live_id', 'livestream_id', 'source_live_id'])
                    ->contains(fn ($key) => filled($raw[$key] ?? null));
                $category = $service->suggestedCategory($entry);
                $house = in_array($category, self::HOUSE_CATEGORIES, true)
                    && (! filled($entry->whatnot_order_id) || $entry->whatnot_order_id === '-')
                    && ! $hasLivestream
                    && $entry->amount !== null;

                if ($existing && $existing->status === 'approved') {
                    return $house && $existing->target === 'house'
                        && $existing->show_id === null
                        && $existing->streamer_id === null ? 'matched' : 'flagged';
                }
                if (! $house) {
                    if ($existing) return 'unmatched';
                    // A category is not evidence of a show or recipient.
                    $payload = [
                        'whatnot_ledger_entry_id' => $entry->id,
                        'category' => $category,
                        'target' => 'unassigned', 'show_id' => null,
                        'streamer_id' => null, 'business_id' => null,
                        'status' => 'pending',
                        'reason' => 'Automatic category only; awaiting verified show/recipient evidence',
                        'submitted_by' => null, 'reviewed_by' => null, 'reviewed_at' => null,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                    DB::table('ledger_attributions')->insert($payload);
                    DB::table('ledger_attribution_events')->insert([
                        'whatnot_ledger_entry_id' => $entry->id, 'actor_id' => null,
                        'action' => 'auto_categorized', 'before' => null,
                        'after' => json_encode($payload), 'reason' => $payload['reason'],
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    return 'categorized';
                }
                // A suggestion someone already reviewed (e.g. rejected) is their call.
                if ($existing && $existing->reviewed_by !== null) return 'manual';
                // Earlier automatic suggestions (pending, nobody's decision) are upgraded.
                $payload = [
                    'category' => $category, 'target' => 'house',
                    'show_id' => null, 'streamer_id' => null, 'business_id' => $existing->business_id ?? null,
                    'status' => 'approved',
                    'reason' => 'Automatic house rule: '.$category.' with no order or livestream',
                    'submitted_by' => null, 'reviewed_by' => null, 'reviewed_at' => now(),
                    'updated_at' => now(),
                ];
                if ($existing) {
                    DB::table('ledger_attributions')->where('id', $existing->id)->update($payload);
                } else {
                    DB::table('ledger_attributions')->insert($payload + ['whatnot_ledger_entry_id' => $entry->id, 'created_at' => now()]);
                }
                DB::table('ledger_attribution_events')->insert([
                    'whatnot_ledger_entry_id' => $entry->id, 'actor_id' => null,
                    'action' => 'auto_house', 'before' => $existing ? json_encode($existing) : null,
                    'after' => json_encode($payload), 'reason' => $payload['reason'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                return 'matched';
            }

            $members = $show->streamers()->get(['streamers.id', 'streamers.business_id']);
            $primary = $members->filter(fn ($m) => (bool) $m->pivot->is_primary)->values();
            $streamer = $primary->count() === 1 ? $primary->first() : null;
            // A business owns the money only when every streamer on the show
            // belongs to it; a mixed crew needs a human decision.
            // (A crew with no business at all is fine: the tip is the streamer's.)
            $businesses = $members->pluck('business_id')->map(fn ($id) => $id === null ? null : (int) $id)->unique();
            $businessResolved = $streamer !== null && $businesses->count() === 1;
            // Verified show does not imply a verified recipient. Hold the
            // allocation for review until exactly one primary is assigned.
            $recipientResolved = $streamer !== null && $businessResolved;
            $payload = [
                'category' => $service->suggestedCategory($entry),
                'target' => 'show', 'show_id' => $show->id,
                'streamer_id' => $streamer?->id,
                'business_id' => $businessResolved ? $streamer->business_id : null, // null = no business
                'status' => $recipientResolved ? 'approved' : 'pending', 'reason' => match (true) {
                    $recipientResolved => 'Automatic verified source livestream/order evidence + channel match',
                    $streamer === null => 'Verified show; awaiting exactly one primary streamer',
                    default => 'Verified show; streamers do not all belong to one business',
                },
                'submitted_by' => null, 'reviewed_by' => null, 'reviewed_at' => null,
                'updated_at' => now(),
            ];
            if ($existing) {
                // A new source match must never silently move an approved financial allocation.
                if ($existing->status === 'approved' &&
                    ($existing->target !== 'show' || (int) $existing->show_id !== (int) $show->id)) {
                    return 'flagged';
                }
                // Preserve approved financial attribution. Reassignment requires an
                // explicit correction, not a background sync or profile change.
                if ($existing->status === 'approved' && $existing->target === 'show' &&
                    (int) $existing->show_id === (int) $show->id) {
                    return 'matched';
                }
                DB::table('ledger_attributions')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('ledger_attributions')->insert($payload + [
                    'whatnot_ledger_entry_id' => $entry->id, 'created_at' => now(),
                ]);
            }
            if (! $existing || $existing->show_id !== $show->id || $existing->streamer_id !== $streamer?->id) {
                DB::table('ledger_attribution_events')->insert([
                    'whatnot_ledger_entry_id' => $entry->id, 'actor_id' => null,
                    'action' => 'auto_matched', 'before' => $existing ? json_encode($existing) : null,
                    'after' => json_encode($payload), 'reason' => $payload['reason'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return 'matched';
        });
    }
}
