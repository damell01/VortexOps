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
                // Only explicit, orderless, livestream-less promotional charges
                // can be classified as house overhead without show evidence.
                // Other fees, refunds and sales remain unmatched for review.
                $type = strtolower(trim((string) $entry->transaction_type));
                $raw = is_array($entry->raw_data) ? $entry->raw_data : [];
                $hasLivestream = collect(['live_id', 'whatnot_live_id', 'livestream_id', 'source_live_id'])
                    ->contains(fn ($key) => filled($raw[$key] ?? null));
                $houseMarketing = in_array($type, ['marketing', 'promotion', 'promotions', 'advertising', 'advertisement'], true)
                    && ! filled($entry->whatnot_order_id)
                    && ! $hasLivestream
                    && (float) $entry->amount < 0;

                if ($existing && $existing->status === 'approved') {
                    return $houseMarketing && $existing->target === 'house'
                        && $existing->category === 'marketing'
                        && $existing->show_id === null
                        && $existing->streamer_id === null ? 'matched' : 'flagged';
                }
                if (! $houseMarketing) {
                    // Classification is independent of verified financial ownership.
                    // Keep the recipient unresolved; a later verified livestream/order
                    // can promote this automatic pending row to an approved show match.
                    // Never modify an existing attribution, including manual reviews.
                    if ($existing) return 'unmatched';
                    $payload = [
                        'whatnot_ledger_entry_id' => $entry->id,
                        'category' => $service->suggestedCategory($entry),
                        'target' => 'unassigned',
                        'show_id' => null, 'streamer_id' => null, 'business_id' => null,
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
                if ($existing) return 'unmatched';
                $payload = [
                    'whatnot_ledger_entry_id' => $entry->id,
                    'category' => 'marketing', 'target' => 'house',
                    'show_id' => null, 'streamer_id' => null, 'business_id' => null,
                    'status' => 'approved',
                    'reason' => 'Automatic explicit orderless marketing debit; no show or business inferred',
                    'submitted_by' => null, 'reviewed_by' => null, 'reviewed_at' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ];
                DB::table('ledger_attributions')->insert($payload);
                DB::table('ledger_attribution_events')->insert([
                    'whatnot_ledger_entry_id' => $entry->id, 'actor_id' => null,
                    'action' => 'auto_matched', 'before' => null,
                    'after' => json_encode($payload), 'reason' => $payload['reason'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                return 'matched';
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
