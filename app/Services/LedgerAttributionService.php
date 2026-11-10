<?php

namespace App\Services;

use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotLedgerEntry;
use Illuminate\Support\Facades\DB;
use App\Support\ChannelContext;
use Illuminate\Validation\ValidationException;

class LedgerAttributionService
{
    public const CATEGORIES = ['tips', 'sales', 'refunds', 'fees', 'shipping', 'giveaways', 'bonus', 'marketing', 'correction', 'payouts', 'other'];

    public function suggestedShow(WhatnotLedgerEntry $entry): ?Show
    {
        // A source-provided livestream identifier can establish ownership even
        // when Whatnot omits the order ID (notably on some adjustments).
        // Never derive a livestream ID from a URL, timestamp or imported show link.
        $raw = is_array($entry->raw_data) ? $entry->raw_data : [];
        $explicit = [];
        foreach (['live_id', 'whatnot_live_id', 'livestream_id', 'source_live_id'] as $field) {
            $value = $raw[$field] ?? null;
            if (is_string($value) && preg_match('/^[a-f0-9-]{32,36}$/i', $value)) {
                $explicit[] = strtolower($value);
            }
        }
        if (count(array_unique($explicit)) > 1) {
            return null; // Conflicting source identifiers: never silently choose one.
        }
        if ($explicit !== [] && $entry->whatnot_channel_id !== null) {
            $matches = Show::query()
                ->where('whatnot_channel_id', $entry->whatnot_channel_id)
                ->whereRaw('LOWER(whatnot_show_id) = ?', [$explicit[0]])
                ->limit(2)->get();
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        if (! filled($entry->whatnot_order_id) || $entry->whatnot_order_id === '-') {
            return null;
        }

        // A single imported show-order link is not proof of ownership.
        // Historical Seller Hub imports have associated the same order with
        // hundreds of shows. Require explicit order-level livestream evidence.
        $rows = DB::table('whatnot_show_orders as orders')
            ->join('shows', 'shows.id', '=', 'orders.show_id')
            ->where('orders.whatnot_order_id', $entry->whatnot_order_id)
            ->get([
                'orders.show_id',
                'orders.raw_data',
                'shows.whatnot_show_id',
                'shows.whatnot_channel_id',
            ]);

        if ($rows->isEmpty() || $entry->whatnot_channel_id === null) {
            return null;
        }

        $verified = [];
        foreach ($rows as $row) {
            $raw = json_decode((string) $row->raw_data, true);
            if (! is_array($raw)) {
                continue;
            }

            if (VerifiedOrderSource::matches($raw, (string) $entry->whatnot_order_id, (string) $row->whatnot_show_id)) {
                if ((int) $row->whatnot_channel_id !== (int) $entry->whatnot_channel_id) return null;
                $verified[(int) $row->show_id] = true;
                continue;
            }

            $liveIds = [];
            foreach (['live_id', 'whatnot_live_id', 'livestream_id', 'source_live_id'] as $key) {
                $value = $raw[$key] ?? null;
                if (is_string($value) && preg_match('/^[a-f0-9-]{32,36}$/i', $value)) {
                    $liveIds[] = strtolower($value);
                }
            }

            if ($liveIds === []) {
                continue;
            }

            // Conflicting explicit identifiers must never auto-approve.
            if (count(array_unique($liveIds)) !== 1 ||
                strtolower((string) $row->whatnot_show_id) !== $liveIds[0] ||
                (int) $row->whatnot_channel_id !== (int) $entry->whatnot_channel_id) {
                return null;
            }
            $verified[(int) $row->show_id] = true;
        }

        // Explicit evidence must resolve to exactly one show. No fallback
        // to timestamp, channel or merely imported show-order associations.
        return count($verified) === 1 ? Show::find(array_key_first($verified)) : null;
    }

    public function suggestedCategory(WhatnotLedgerEntry $entry): string
    {
        // Whole words only: "Multiple orders" is not a tip, "Coffee" is not a fee.
        $type = strtolower(str_replace('_', ' ', trim((string) $entry->transaction_type)));
        $has = fn (string $words): bool => (bool) preg_match('/\\b(?:' . $words . ')\\b/', $type);

        return match (true) {
            $has('tips?') => 'tips',
            $has('payouts?|transfers?|withdrawals?') => 'payouts',
            // Carrier adjustments, label costs and their refunds are shipping, not order refunds.
            $has('shipping|postage|carrier|labels?') => 'shipping',
            // A refunded promo or bonus is still marketing / bonus money, not an order refund.
            $has('marketing|promotions?|promoted|advertising|advertisement|ads?') => 'marketing',
            $has('bonus(?:es)?') => 'bonus',
            $has('refunds?|refunded|chargebacks?') => 'refunds',
            $has('giveaways?') => 'giveaways',
            $has('fees?|commissions?') => 'fees',
            $has('sales?|earnings?|auction|buy it now') => 'sales',
            $has('adjustments?|corrections?') => 'correction',
            default => 'other',
        };
    }

    public function save(WhatnotLedgerEntry $entry, array $data, int $actorId): void
    {
        if (ChannelContext::isScoped() && (int) $entry->whatnot_channel_id !== (int) ChannelContext::currentId()) {
            throw ValidationException::withMessages(['attribution' => 'This entry is outside your active channel.']);
        }
        if (! in_array($data['category'] ?? '', self::CATEGORIES, true) ||
            ! in_array($data['target'] ?? '', ['show', 'streamer', 'house', 'unassigned'], true) ||
            trim((string) ($data['reason'] ?? '')) === '') {
            throw ValidationException::withMessages(['attribution' => 'Select a valid category and destination, and enter a reason.']);
        }

        // Buyer tips always belong to their verified show and its primary streamer.
        // Never allow a tip to be categorized as a house expense or credited to
        // a streamer without the show that generated it.
        $isTip = $this->suggestedCategory($entry) === 'tips' || ($data['category'] ?? null) === 'tips';
        if ($isTip && ! in_array($data['target'], ['show', 'unassigned'], true)) {
            throw ValidationException::withMessages(['attribution' => 'Tips must be assigned to their show and primary streamer. Leave unverified tips unassigned for review; House is not allowed.']);
        }

        $show = null;
        $streamer = null;
        $businessId = null;
        if ($data['target'] === 'show') {
            $show = Show::findOrFail((int) ($data['show_id'] ?? 0));
            if ($show->whatnot_channel_id !== $entry->whatnot_channel_id) {
                throw ValidationException::withMessages(['attribution' => 'The show must belong to the ledger entry channel.']);
            }
            $primary = $show->streamers()->wherePivot('is_primary', true)->get();
            if ($primary->count() > 1) {
                throw ValidationException::withMessages(['attribution' => 'This show has multiple primary streamers. Fix the show first.']);
            }
            $streamer = $primary->first();
            if ($isTip && ! $streamer) {
                throw ValidationException::withMessages(['attribution' => 'Assign a primary streamer to this show before attributing its tips.']);
            }
            $businessId = $streamer?->business_id;
        } elseif ($data['target'] === 'streamer') {
            $streamer = Streamer::query()->where('status', 'active')->findOrFail((int) ($data['streamer_id'] ?? 0));
            $businessId = $streamer->business_id;
        } elseif ($data['target'] === 'house') {
            $businessId = filled($data['business_id'] ?? null) ? (int) $data['business_id'] : null;
            if ($businessId && ! DB::table('businesses')->where('id', $businessId)->exists()) {
                throw ValidationException::withMessages(['attribution' => 'Select a valid business.']);
            }
        }
        DB::transaction(function () use ($entry, $data, $actorId, $show, $streamer, $businessId) {
            $before = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->lockForUpdate()->first();
            if ($before && $before->status === 'pending' && $before->submitted_by !== null && (int) $before->submitted_by !== $actorId) {
                throw ValidationException::withMessages(['attribution' => 'Another administrator has a pending correction. Review it before submitting another.']);
            }
            $payload = [
                'category' => $data['category'], 'target' => $data['target'],
                'show_id' => $show?->id, 'streamer_id' => $streamer?->id,
                'business_id' => $businessId, 'status' => 'pending',
                'reason' => trim($data['reason']), 'submitted_by' => $actorId,
                'reviewed_by' => null, 'reviewed_at' => null, 'updated_at' => now(),
            ];
            if ($before) {
                DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->update($payload);
            } else {
                DB::table('ledger_attributions')->insert($payload + ['whatnot_ledger_entry_id' => $entry->id, 'created_at' => now()]);
            }
            $this->event($entry->id, $actorId, 'submitted', $before, $payload, $data['reason']);
        });
        // Ledger page totals and reports show the change straight away.
        \App\Filament\Resources\WhatnotLedgerResource\Pages\ListWhatnotLedgerEntries::forgetReports();
        $this->syncShowTips($entry);
    }

    /**
     * A tip approved to a show lands on the show: its tips become at least
     * what the ledger has approved for it (never added on top, the same money
     * is not counted twice), and draft pay for that show is worked out again
     * so the run includes it. Pay already finalized or paid is never changed;
     * "Changed after payout" lists the difference instead.
     */
    public function syncShowTips(WhatnotLedgerEntry $entry): void
    {
        $row = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->first(['show_id', 'category', 'status']);
        $isTip = in_array(strtolower(trim((string) $entry->transaction_type)), ['tip', 'tips'], true) || ($row->category ?? null) === 'tips';
        if (! $row || ! $row->show_id || ! $isTip || $row->status !== 'approved') return;

        $show = \App\Models\Show::query()->with(['streamers', 'channel', 'streamerLogEntry'])->find($row->show_id);
        if (! $show) return;
        $payouts = app(\App\Services\PayoutService::class);
        $tips = $payouts->showTips($show);
        if ($tips > round((float) $show->tips, 2) + 0.004) {
            if (method_exists($show, 'trackChanges')) $show->trackChanges(['tips' => $tips], 'ledger_tips');
            $show->forceFill(['tips' => $tips])->save();
        }

        $drafts = \App\Models\Payout::query()->where('show_id', $show->id)->whereHas('batch', fn ($q) => $q->where('status', 'draft'))->with('batch')->get();
        if ($drafts->isEmpty()) return;
        try {
            $payouts->calculateForShow($show);
            $drafts->pluck('batch')->filter()->unique('id')->each(fn ($run) => $run->recalculateTotal());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function review(WhatnotLedgerEntry $entry, int $actorId, bool $approve): void
    {
        if (ChannelContext::isScoped() && (int) $entry->whatnot_channel_id !== (int) ChannelContext::currentId()) {
            throw ValidationException::withMessages(['attribution' => 'This entry is outside your active channel.']);
        }
        DB::transaction(function () use ($entry, $actorId, $approve) {
            $row = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->lockForUpdate()->first();
            if (! $row || $row->status !== 'pending') {
                throw ValidationException::withMessages(['attribution' => 'Only pending corrections can be reviewed.']);
            }
            if ($row->submitted_by === $actorId) {
                throw ValidationException::withMessages(['attribution' => 'A different administrator must approve or reject this correction.']);
            }
            if ($approve) {
                // An automatically suggested category is not evidence of a recipient.
                // In particular, a house marketing suggestion without a business
                // must be explicitly corrected and reviewed before approval.
                if ($row->target === 'unassigned') {
                    throw ValidationException::withMessages(['attribution' => 'Assign a verified destination before approval.']);
                }
                if ($row->target === 'house' && $row->business_id === null) {
                    throw ValidationException::withMessages(['attribution' => 'Select the receiving business before approving a house expense.']);
                }
                if ($row->target === 'show' && ($row->show_id === null || $row->streamer_id === null)) {
                    throw ValidationException::withMessages(['attribution' => 'Assign a verified show and its primary streamer before approval.']);
                }
                if ($row->target === 'streamer' && $row->streamer_id === null) {
                    throw ValidationException::withMessages(['attribution' => 'Select a streamer before approval.']);
                }
                $isTip = $this->suggestedCategory($entry) === 'tips' || $row->category === 'tips';
                if ($isTip && ($row->target !== 'show' || $row->show_id === null || $row->streamer_id === null)) {
                    throw ValidationException::withMessages(['attribution' => 'Tips require a verified show and primary streamer; house and direct streamer allocations are not permitted.']);
                }
            }
            $status = $approve ? 'approved' : 'rejected';
            DB::table('ledger_attributions')->where('id', $row->id)->update([
                'status' => $status, 'reviewed_by' => $actorId, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($entry->id, $actorId, $status, $row, ['status' => $status], null);
        });
        // Ledger page totals and reports show the change straight away.
        \App\Filament\Resources\WhatnotLedgerResource\Pages\ListWhatnotLedgerEntries::forgetReports();
        $this->syncShowTips($entry);
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
