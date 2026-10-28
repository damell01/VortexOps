<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LinkLedgerToUniqueShowCandidates extends Command
{
    protected $signature = 'ledger:link-unique-shows {--limit=10000 : Ledger entries to inspect} {--after-id=0 : Resume after ledger ID} {--submit : Save unique candidates as pending review}';
    protected $description = 'Connect ledger entries to unique same-channel show candidates, without approving uncertain ownership';

    public function handle(): int
    {
        $limit = min(100000, max(1, (int) $this->option('limit')));
        $after = max(0, (int) $this->option('after-id'));
        $submit = (bool) $this->option('submit');
        $counts = ['checked'=>0,'unique'=>0,'ambiguous'=>0,'missing'=>0,'conflicting_channel'=>0,'already_attributed'=>0,'submitted_pending'=>0];
        $lastId = $after;
        DB::table('whatnot_ledger_entries')->where('id', '>', $after)
            ->whereNotNull('whatnot_order_id')->whereNotIn('whatnot_order_id', ['', '-'])
            ->orderBy('id')->chunkById(200, function ($entries) use (&$counts, &$lastId, $limit, $submit) {
                foreach ($entries as $entry) {
                    if ($counts['checked'] >= $limit) return false;
                    $counts['checked']++; $lastId = $entry->id;
                    $all = DB::table('whatnot_show_orders as o')->join('shows as s','s.id','=','o.show_id')
                        ->where('o.whatnot_order_id', $entry->whatnot_order_id)
                        ->get(['o.show_id','s.whatnot_channel_id']);
                    if ($all->isEmpty()) { $counts['missing']++; continue; }
                    $same = $all->filter(fn ($r) => $entry->whatnot_channel_id !== null && (int) $r->whatnot_channel_id === (int) $entry->whatnot_channel_id)
                        ->pluck('show_id')->unique()->values();
                    if ($same->isEmpty()) { $counts['conflicting_channel']++; continue; }
                    if ($same->count() !== 1) { $counts['ambiguous']++; continue; }
                    $counts['unique']++;
                    if (! $submit) continue;
                    DB::transaction(function () use ($entry, $same, &$counts) {
                        $existing = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->lockForUpdate()->first();
                        if ($existing) { $counts['already_attributed']++; return; }
                        DB::table('ledger_attributions')->insert([
                            'whatnot_ledger_entry_id' => $entry->id,
                            'category' => app(\App\Services\LedgerAttributionService::class)->suggestedCategory(\App\Models\WhatnotLedgerEntry::findOrFail($entry->id)),
                            'target' => 'show', 'show_id' => (int) $same->first(),
                            'streamer_id' => null, 'business_id' => null,
                            'status' => 'pending',
                            'reason' => 'Unique same-channel imported show-order candidate; not independently verified',
                            'submitted_by' => null, 'reviewed_by' => null, 'reviewed_at' => null,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $counts['submitted_pending']++;
                    });
                }
            });
        $this->line(json_encode($counts + ['last_id'=>$lastId]));
        $this->warn($submit ? 'Candidates linked as PENDING only. Not approved for financial attribution.' : 'Dry run: no database changes. Use --submit to create pending show links.');
        return self::SUCCESS;
    }
}
