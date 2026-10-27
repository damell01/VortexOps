<?php

namespace App\Services;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotLedgerEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerAttributionService
{
    public const CATEGORIES = ['tips', 'sales', 'refunds', 'fees', 'shipping', 'giveaways', 'bonus', 'marketing', 'other'];

    public function save(WhatnotLedgerEntry $entry, array $data, int $actorId): void
    {
        if (! in_array($data['category'] ?? '', self::CATEGORIES, true) ||
            ! in_array($data['target'] ?? '', ['show', 'streamer', 'house', 'unassigned'], true) ||
            trim((string) ($data['reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['attribution' => 'Select a valid category and destination, and enter a reason.']);
        }

        $show = null;
        $streamer = null;
        $businessId = null;
        if ($data['target'] === 'show') {
            $show = Show::findOrFail((int) ($data['show_id'] ?? 0));
            if ($entry->whatnot_channel_id && $show->whatnot_channel_id !== $entry->whatnot_channel_id) {
                throw ValidationException::withMessages(['attribution' => 'The show must belong to the ledger entry channel.']);
            }
            $primary = $show->streamers()->wherePivot('is_primary', true)->get();
            if ($primary->count() > 1) {
                throw ValidationException::withMessages(['attribution' => 'This show has multiple primary streamers. Fix the show first.']);
            }
            $streamer = $primary->first();
            $businessId = $streamer?->business_id;
        } elseif ($data['target'] === 'streamer') {
            $streamer = Streamer::findOrFail((int) ($data['streamer_id'] ?? 0));
            $businessId = $streamer->business_id;
        } elseif ($data['target'] === 'house') {
            $businessId = filled($data['business_id'] ?? null) ? (int) $data['business_id'] : null;
            if ($businessId && ! DB::table('businesses')->where('id', $businessId)->exists()) {
                throw ValidationException::withMessages(['attribution' => 'Select a valid business.']);
            }
        }
        DB::transaction(function () use ($entry, $data, $actorId, $show, $streamer, $businessId) {
            $before = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->lockForUpdate()->first();
            $payload = [
                'category' => $data['category'], 'target' => $data['target'],
                'show_id' => $show?->id, 'streamer_id' => $streamer?->id,
                'business_id' => $businessId, 'status' => 'pending',
                'reason' => trim($data['reason']), 'submitted_by' => $actorId,
                'reviewed_by' => null, 'reviewed_at' => null, 'updated_at' => now(),
            ];
            DB::table('ledger_attributions')->updateOrInsert(['whatnot_ledger_entry_id' => $entry->id], $payload + ['created_at' => now()]);
            $this->event($entry->id, $actorId, 'submitted', $before, $payload, $data['reason']);
        });
    }

    public function review(WhatnotLedgerEntry $entry, int $actorId, bool $approve): void
    {
        DB::transaction(function () use ($entry, $actorId, $approve) {
            $row = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->lockForUpdate()->first();
            if (! $row || $row->status !== 'pending') {
                throw ValidationException::withMessages(['attribution' => 'Only pending corrections can be reviewed.']);
            }
            if ($row->submitted_by === $actorId) {
                throw ValidationException::withMessages(['attribution' => 'A different administrator must approve or reject this correction.']);
            }
            $status = $approve ? 'approved' : 'rejected';
            DB::table('ledger_attributions')->where('id', $row->id)->update([
                'status' => $status, 'reviewed_by' => $actorId, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($entry->id, $actorId, $status, $row, ['status' => $status], null);
        });
    }

    private function event(int $entryId, int $actorId, string $action, mixed $before, mixed $after, ?string $reason): void
    {
        DB::table('ledger_attribution_events')->insert([
            'whatnot_ledger_entry_id' => $entryId, 'actor_id' => $actorId, 'action' => $action,
            'before' => $before ? json_encode($before) : null, 'after' => json_encode($after),
            'reason' => $reason, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
