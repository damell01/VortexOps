<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotLedgerEntry;
use App\Services\LedgerAttributionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportVerifiedLedgerShowOrders extends Command
{
    protected $signature = 'ledger:import-verified-orders {file : CSV with order_id,channel_id,show_id,evidence} {--submit : Submit pending corrections; default is dry run} {--actor= : Administrator user ID submitting corrections}';
    protected $description = 'Validate independent order-to-show evidence and optionally submit pending ledger corrections';

    public function handle(LedgerAttributionService $service): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) { $this->error('CSV not readable'); return self::FAILURE; }
        $fp = fopen($path, 'rb');
        $header = fgetcsv($fp);
        $required = ['order_id', 'channel_id', 'show_id', 'evidence'];
        if (! $header || array_diff($required, $header)) { fclose($fp); $this->error('Required columns: '.implode(',', $required)); return self::FAILURE; }
        $map = array_flip($header); $records = []; $errors = []; $line = 1;
        while (($row = fgetcsv($fp)) !== false) {
            $line++;
            $order = trim((string) ($row[$map['order_id']] ?? ''));
            $channel = trim((string) ($row[$map['channel_id']] ?? ''));
            $showId = trim((string) ($row[$map['show_id']] ?? ''));
            $evidence = trim((string) ($row[$map['evidence']] ?? ''));
            if (! preg_match('/^[0-9]+$/', $order) || ! ctype_digit($channel) || ! ctype_digit($showId) || mb_strlen($evidence) < 12) { $errors[] = "Line {$line}: invalid order, channel, show or evidence"; continue; }
            $key = $channel.':'.$order;
            if (isset($records[$key]) && $records[$key]['show_id'] !== (int) $showId) { $errors[] = "Line {$line}: conflicting show for {$key}"; continue; }
            $show = Show::find((int) $showId);
            if (! $show || (int) $show->whatnot_channel_id !== (int) $channel) { $errors[] = "Line {$line}: show/channel mismatch"; continue; }
            $records[$key] = ['order_id' => $order, 'channel_id' => (int) $channel, 'show_id' => (int) $showId, 'evidence' => $evidence];
        }
        fclose($fp);
        if ($errors) { foreach (array_slice($errors, 0, 30) as $error) $this->error($error); $this->error(count($errors).' CSV errors; nothing submitted'); return self::FAILURE; }
        $actor = (int) $this->option('actor');
        if ($this->option('submit') && (! $actor || ! DB::table('users')->where('id', $actor)->exists())) { $this->error('Provide a valid --actor administrator ID'); return self::FAILURE; }
        $counts = ['orders' => count($records), 'eligible_entries' => 0, 'already_approved' => 0, 'pending_other' => 0, 'submitted' => 0];
        foreach ($records as $record) {
            WhatnotLedgerEntry::query()->where('whatnot_order_id', $record['order_id'])->where('whatnot_channel_id', $record['channel_id'])->orderBy('id')->chunkById(200, function ($entries) use ($record, $service, $actor, &$counts) {
                foreach ($entries as $entry) {
                    $current = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->first();
                    if ($current && $current->status === 'approved') { $counts['already_approved']++; continue; }
                    if ($current && $current->status === 'pending' && (int) $current->submitted_by !== $actor) { $counts['pending_other']++; continue; }
                    $counts['eligible_entries']++;
                    if (! $this->option('submit')) continue;
                    $service->save($entry, ['category' => $service->suggestedCategory($entry), 'target' => 'show', 'show_id' => $record['show_id'], 'reason' => 'Verified source: '.$record['evidence']], $actor);
                    $counts['submitted']++;
                }
            });
        }
        $this->line(json_encode($counts));
        $this->warn($this->option('submit') ? 'Corrections submitted as PENDING; separate administrator must approve.' : 'DRY RUN ONLY: no data changed. Use --submit --actor=ID after reviewing evidence.');
        return self::SUCCESS;
    }
}
