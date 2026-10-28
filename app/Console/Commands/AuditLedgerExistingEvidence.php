<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Inventory existing financial/order evidence without modifying imports or attributions.
 * A candidate is never treated as verified show ownership.
 */
class AuditLedgerExistingEvidence extends Command
{
    protected $signature = 'ledger:audit-existing-evidence {--limit=1000 : Maximum distinct ledger orders (max 10000)} {--examples=20 : Sample candidates (max 50)}';
    protected $description = 'Read-only correlation of existing ledger, show-order and ingestion evidence';

    public function handle(): int
    {
        $limit = min(10000, max(1, (int) $this->option('limit')));
        $examples = min(50, max(0, (int) $this->option('examples')));
        $ids = DB::table('whatnot_ledger_entries')->whereNotNull('whatnot_order_id')
            ->whereNotIn('whatnot_order_id', ['', '-'])
            ->distinct()->orderBy('whatnot_order_id')->limit($limit)->pluck('whatnot_order_id');
        $counts = array_fill_keys([
            'orders_checked', 'no_order_rows', 'single_show_candidate', 'multiple_show_candidates',
            'channel_conflicts', 'amount_agrees_on_single_show', 'item_text_agrees_on_single_show',
            'order_detail_hash_agrees_on_single_show', 'has_shipment_identifier',
            'raw_order_payload_has_explicit_live_id', 'verified_show_ownership'
        ], 0);
        $samples = [];

        foreach ($ids as $orderId) {
            $counts['orders_checked']++;
            $ledger = DB::table('whatnot_ledger_entries')->where('whatnot_order_id', $orderId)
                ->get(['whatnot_channel_id', 'amount', 'order_hash', 'message', 'listing_id']);
            $orders = DB::table('whatnot_show_orders as o')
                ->join('shows as s', 's.id', '=', 'o.show_id')
                ->where('o.whatnot_order_id', $orderId)
                ->get(['o.show_id', 'o.raw_data', 'o.whatnot_order_detail_url', 'o.whatnot_shipment_id',
                    'o.item_name', 'o.total_price', 'o.net_amount', 's.whatnot_channel_id', 's.whatnot_show_id']);
            if ($orders->isEmpty()) {
                $counts['no_order_rows']++;
                continue;
            }

            $showIds = $orders->pluck('show_id')->unique();
            $single = $showIds->count() === 1;
            $counts[$single ? 'single_show_candidate' : 'multiple_show_candidates']++;
            $channels = $ledger->pluck('whatnot_channel_id')->filter()->unique()->map(fn ($v) => (int) $v)->all();
            if ($orders->every(fn ($o) => ! in_array((int) $o->whatnot_channel_id, $channels, true))) {
                $counts['channel_conflicts']++;
            }
            $amount = false;
            $item = false;
            $hash = false;
            $shipment = false;
            $live = false;
            foreach ($orders as $order) {
                $raw = json_decode((string) $order->raw_data, true);
                if (! is_array($raw)) $raw = [];
                $shipment = $shipment || ! empty($order->whatnot_shipment_id) || ! empty($raw['shipment_id']);
                foreach (['live_id', 'whatnot_live_id', 'livestream_id', 'source_live_id'] as $key) {
                    $v = $raw[$key] ?? null;
                    if (is_string($v) && preg_match('/^[a-f0-9-]{32,36}$/i', $v)) $live = true;
                }
                foreach ($ledger as $entry) {
                    if ($entry->whatnot_channel_id === null ||
                        (int) $entry->whatnot_channel_id !== (int) $order->whatnot_channel_id) continue;
                    if ($entry->amount !== null &&
                        ($order->net_amount !== null || $order->total_price !== null)) {
                        foreach ([$order->net_amount, $order->total_price] as $price) {
                            if ($price !== null && abs((float) $entry->amount - (float) $price) < 0.005) $amount = true;
                        }
                    }
                    if (strlen(trim((string) $order->item_name)) >= 5 &&
                        stripos((string) $entry->message, trim((string) $order->item_name)) !== false) $item = true;
                    if (strlen(trim((string) $entry->order_hash)) >= 8 &&
                        str_contains((string) $order->whatnot_order_detail_url, (string) $entry->order_hash)) $hash = true;
                }
            }
            if ($single && $amount) $counts['amount_agrees_on_single_show']++;
            if ($single && $item) $counts['item_text_agrees_on_single_show']++;
            if ($single && $hash) $counts['order_detail_hash_agrees_on_single_show']++;
            if ($shipment) $counts['has_shipment_identifier']++;
            if ($live) $counts['raw_order_payload_has_explicit_live_id']++;
            // All these fields can come from a channel-wide scrape. None independently
            // establishes show ownership, so do not count a verified link here.
            if (count($samples) < $examples && ($single || $hash || $live)) {
                $samples[] = [$orderId, $showIds->count(), $amount ? 'yes' : 'no',
                    $item ? 'yes' : 'no', $hash ? 'yes' : 'no', $live ? 'yes' : 'no'];
            }
        }
        $this->table(['Metric', 'Count'], collect($counts)->map(fn ($n, $key) => [$key, $n])->values()->all());
        $this->table(['Order', 'Show count', 'Amount', 'Item', 'Hash', 'Explicit live ID'], $samples);
        $this->warn('READ ONLY. Correlations are review candidates, not proof of originating livestream. No database changes made.');
        return self::SUCCESS;
    }
}
