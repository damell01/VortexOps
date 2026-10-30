<?php

namespace App\Services;

use App\Models\WhatnotLedgerEntry;
use Illuminate\Support\Facades\DB;

class LedgerAutoMatcher
{
    public function match(WhatnotLedgerEntry $entry): string
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
                if ($existing && $existing->submitted_by === null &&
                    $existing->status === 'approved' && $existing->target === 'show') {
                    DB::table('ledger_attributions')->where('id', $existing->id)->update([
                        'status' => 'pending',
                        'reason' => 'Legacy automatic show match requires independent verification',
                        'reviewed_by' => null, 'reviewed_at' => null, 'updated_at' => now(),
                    ]);
                    DB::table('ledger_attribution_events')->insert([
                        'whatnot_ledger_entry_id' => $entry->id, 'actor_id' => null,
                        'action' => 'auto_match_flagged', 'before' => json_encode($existing),
                        'after' => json_encode(['status' => 'pending', 'show_id' => $existing->show_id]),
                        'reason' => 'Unverified historical automatic show attribution',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    return 'flagged';
                }
                return 'unmatched';
            }

            $primary = $show->streamers()->wherePivot('is_primary', true)->get();
            $streamer = $primary->count() === 1 ? $primary->first() : null;
            $payload = [
                'category' => $service->suggestedCategory($entry),
                'target' => 'show', 'show_id' => $show->id,
                'streamer_id' => $streamer?->id,
                'business_id' => $streamer?->business_id,
                'status' => 'approved', 'reason' => 'Automatic verified source livestream/order evidence + channel match',
                'submitted_by' => null, 'reviewed_by' => null, 'reviewed_at' => null,
                'updated_at' => now(),
            ];
            if ($existing) {
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
