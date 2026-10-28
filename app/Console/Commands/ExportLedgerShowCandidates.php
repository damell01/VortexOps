<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportLedgerShowCandidates extends Command
{
    protected $signature = 'ledger:export-show-candidates {--limit=5000 : Ledger entries to inspect (max 100000)} {--after-id=0 : Resume after ledger ID} {--output= : CSV output path (defaults to storage/app/ledger-show-candidates.csv)}';
    protected $description = 'Export existing ledger-to-show review candidates without modifying financial data';

    public function handle(): int
    {
        $limit = min(100000, max(1, (int) $this->option('limit')));
        $after = max(0, (int) $this->option('after-id'));
        $path = (string) ($this->option('output') ?: storage_path('app/ledger-show-candidates.csv'));
        $dir = dirname($path);
        if (! is_dir($dir) || ! is_writable($dir)) {
            $this->error('Output directory does not exist or is not writable.');
            return self::FAILURE;
        }
        $file = fopen($path, 'wb');
        if (! $file) return self::FAILURE;
        fputcsv($file, ['ledger_id','order_id','channel_id','ledger_amount','ledger_message','existing_target',
            'existing_show_id','existing_status','candidate_show_id','candidate_show_date',
            'candidate_show_channel','candidate_count','amount_matches','item_matches',
            'explicit_live_id_matches','classification']);
        $processed = 0;
        $lastId = $after;
        $stats = ['no_order_rows'=>0,'ambiguous'=>0,'review_candidate'=>0,'explicit_live_id_candidate'=>0];
        try {
            DB::table('whatnot_ledger_entries')->where('id', '>', $after)
                ->whereNotNull('whatnot_order_id')->whereNotIn('whatnot_order_id', ['', '-'])
                ->orderBy('id')->chunkById(250, function ($entries) use ($file, $limit, &$processed, &$lastId, &$stats) {
                    foreach ($entries as $entry) {
                        if ($processed >= $limit) return false;
                        $processed++;
                        $lastId = $entry->id;
                        $existing = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $entry->id)->first();
                        $orders = DB::table('whatnot_show_orders as o')
                            ->join('shows as s', 's.id', '=', 'o.show_id')
                            ->where('o.whatnot_order_id', $entry->whatnot_order_id)
                            ->get(['o.show_id','o.raw_data','o.net_amount','o.total_price','o.item_name',
                                's.show_date','s.whatnot_channel_id','s.whatnot_show_id']);
                        $sameChannel = $orders->filter(fn ($o) => $entry->whatnot_channel_id !== null &&
                            (int) $o->whatnot_channel_id === (int) $entry->whatnot_channel_id);
                        $count = $sameChannel->pluck('show_id')->unique()->count();
                        $classification = $orders->isEmpty() ? 'no_order_rows' :
                            ($count === 1 && $orders->pluck('show_id')->unique()->count() === 1 ? 'review_candidate' : 'ambiguous');
                        $rows = $sameChannel->isEmpty() ? [null] : $sameChannel->all();
                        $hasExplicit = false;
                        foreach ($rows as $order) {
                            $raw = $order ? json_decode((string) $order->raw_data, true) : [];
                            $raw = is_array($raw) ? $raw : [];
                            $live = false;
                            foreach (['live_id','whatnot_live_id','livestream_id','source_live_id'] as $key) {
                                $v = $raw[$key] ?? null;
                                if (is_string($v) && $order && strcasecmp($v, (string) $order->whatnot_show_id) === 0) $live = true;
                            }
                            $hasExplicit = $hasExplicit || $live;
                            $amount = $order && $entry->amount !== null &&
                                collect([$order->net_amount,$order->total_price])->contains(
                                    fn ($v) => $v !== null && abs((float) $v - (float) $entry->amount) < 0.005);
                            $item = $order && strlen(trim((string) $order->item_name)) >= 5 &&
                                stripos((string) $entry->message, trim((string) $order->item_name)) !== false;
                            fputcsv($file, [$entry->id,$entry->whatnot_order_id,$entry->whatnot_channel_id,
                                $entry->amount,$entry->message,$existing->target ?? '',
                                $existing->show_id ?? '',$existing->status ?? '',$order->show_id ?? '',
                                $order->show_date ?? '',$order->whatnot_channel_id ?? '',$count,
                                $amount ? 1 : 0,$item ? 1 : 0,$live ? 1 : 0,$classification]);
                        }
                        if ($hasExplicit && $classification === 'review_candidate') $classification = 'explicit_live_id_candidate';
                        $stats[$classification]++;
                    }
                });
        } finally {
            fclose($file);
        }
        $this->info('Read-only review CSV: '.$path);
        $this->line('Processed: '.$processed.'; last ledger ID: '.$lastId.'; counts: '.json_encode($stats));
        $this->warn('Candidate rows are NOT verified Whatnot show ownership. Existing auto attributions are not validated by this report. No database records changed.');
        return self::SUCCESS;
    }
}
