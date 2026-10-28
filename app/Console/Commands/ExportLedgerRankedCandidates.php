<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExportLedgerRankedCandidates extends Command
{
    protected $signature = 'ledger:rank-candidates {--limit=10000 : Maximum ledger entries (max 100000)} {--after-id=0 : Resume after ledger ID} {--output= : CSV output path}';
    protected $description = 'Rank existing ledger-to-show candidates for human review without modifying financial records';

    public function handle(): int
    {
        $limit = min(100000, max(1, (int) $this->option('limit')));
        $after = max(0, (int) $this->option('after-id'));
        $path = (string) ($this->option('output') ?: storage_path('app/ledger-ranked-candidates.csv'));
        if (! is_dir(dirname($path)) || ! is_writable(dirname($path))) {
            $this->error('Output directory is not writable'); return self::FAILURE;
        }
        $file = fopen($path, 'wb');
        if (! $file) return self::FAILURE;
        fputcsv($file, ['ledger_id','order_id','channel_id','ledger_amount','show_id','show_date','candidate_show_count','amount_match','item_match','classification']);
        $processed = 0; $lastId = $after; $stats = ['no_order_rows'=>0,'cross_channel_only'=>0,'single_candidate'=>0,'multiple_candidates'=>0];
        try {
            DB::table('whatnot_ledger_entries')->where('id', '>', $after)
                ->whereNotNull('whatnot_order_id')->whereNotIn('whatnot_order_id', ['', '-'])
                ->orderBy('id')->chunkById(200, function ($entries) use ($file, $limit, &$processed, &$lastId, &$stats) {
                    foreach ($entries as $entry) {
                        if ($processed >= $limit) return false;
                        $processed++; $lastId = $entry->id;
                        $rows = DB::table('whatnot_show_orders as o')
                            ->join('shows as s', 's.id', '=', 'o.show_id')
                            ->where('o.whatnot_order_id', $entry->whatnot_order_id)
                            ->get(['o.show_id','o.net_amount','o.total_price','o.item_name','s.show_date','s.whatnot_channel_id']);
                        $eligible = $rows->filter(fn ($r) => $entry->whatnot_channel_id !== null &&
                            (int) $r->whatnot_channel_id === (int) $entry->whatnot_channel_id);
                        $groups = $eligible->groupBy('show_id');
                        $count = $groups->count();
                        $classification = $rows->isEmpty() ? 'no_order_rows' :
                            ($count === 0 ? 'cross_channel_only' : ($count === 1 ? 'single_candidate' : 'multiple_candidates'));
                        $stats[$classification]++;
                        if ($count === 0) {
                            fputcsv($file, [$entry->id,$entry->whatnot_order_id,$entry->whatnot_channel_id,$entry->amount,'','','',0,0,$classification]);
                            continue;
                        }
                        foreach ($groups as $showId => $candidates) {
                            $amount = $candidates->contains(fn ($r) => $entry->amount !== null &&
                                collect([$r->net_amount, $r->total_price])->contains(fn ($v) => $v !== null && abs((float) $v - (float) $entry->amount) < 0.005));
                            $item = $candidates->contains(fn ($r) => strlen(trim((string) $r->item_name)) >= 5 &&
                                stripos((string) $entry->message, trim((string) $r->item_name)) !== false);
                            fputcsv($file, [$entry->id,$entry->whatnot_order_id,$entry->whatnot_channel_id,$entry->amount,$showId,
                                $candidates->first()->show_date,$count,(int) $amount,(int) $item,$classification]);
                        }
                    }
                });
        } finally { fclose($file); }
        $this->info('Review CSV: '.$path);
        $this->line('Processed '.$processed.'; last ID '.$lastId.'; '.json_encode($stats));
        $this->warn('No assignments changed. Candidate rows are NOT verified show ownership.');
        return self::SUCCESS;
    }
}
