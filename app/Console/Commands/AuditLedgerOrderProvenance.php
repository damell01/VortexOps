<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only investigation of stored order evidence. Never treats a scraped
 * show/order association as verified ownership by itself.
 */
class AuditLedgerOrderProvenance extends Command
{
    protected $signature = 'ledger:audit-order-provenance {--limit=1000 : Ledger order IDs to inspect (max 10000)} {--examples=15 : Sample order IDs to print}';
    protected $description = 'Read-only audit of ledger order IDs, show-order payloads and ingestion provenance';

    public function handle(): int
    {
        $limit = min(10000, max(1, (int) $this->option('limit')));
        $examples = min(50, max(0, (int) $this->option('examples')));
        $stats = [
            'ledger_orders_checked' => 0,
            'no_captured_order' => 0,
            'one_captured_show' => 0,
            'multiple_captured_shows' => 0,
            'channel_mismatch_only' => 0,
            'order_payload_explicit_live_id' => 0,
            'order_payload_live_id_matches_attached_show' => 0,
            'order_payload_live_id_conflict' => 0,
            'order_payload_no_live_id' => 0,
        ];
        $samples = [];
        $orders = DB::table('whatnot_ledger_entries')
            ->whereNotNull('whatnot_order_id')
            ->whereNotIn('whatnot_order_id', ['', '-'])
            ->select('whatnot_order_id')
            ->distinct()->orderBy('whatnot_order_id')->limit($limit)->pluck('whatnot_order_id');

        foreach ($orders as $orderId) {
            $stats['ledger_orders_checked']++;
            $ledgerChannels = DB::table('whatnot_ledger_entries')
                ->where('whatnot_order_id', $orderId)->whereNotNull('whatnot_channel_id')
                ->distinct()->pluck('whatnot_channel_id')->map(fn ($id) => (int) $id)->all();
            $rows = DB::table('whatnot_show_orders as o')
                ->leftJoin('shows as s', 's.id', '=', 'o.show_id')
                ->where('o.whatnot_order_id', $orderId)
                ->get(['o.id', 'o.show_id', 'o.whatnot_show_url', 'o.raw_data', 's.whatnot_show_id', 's.whatnot_channel_id']);
            if ($rows->isEmpty()) {
                $stats['no_captured_order']++;
                if (count($samples) < $examples) $samples[] = [$orderId, 'not captured', '-', '-'];
                continue;
            }
            $showIds = $rows->pluck('show_id')->filter()->unique();
            $stats[$showIds->count() === 1 ? 'one_captured_show' : 'multiple_captured_shows']++;
            $matching = $rows->filter(fn ($r) => in_array((int) $r->whatnot_channel_id, $ledgerChannels, true));
            if ($matching->isEmpty()) $stats['channel_mismatch_only']++;

            $explicit = [];
            foreach ($rows as $row) {
                $raw = json_decode((string) $row->raw_data, true);
                if (!is_array($raw)) $raw = [];
                // Only explicit, named source fields count. Do not mine arbitrary
                // text/URLs for UUIDs or assume the attached show's ID is evidence.
                foreach (['live_id', 'whatnot_live_id', 'livestream_id', 'source_live_id'] as $field) {
                    $v = $raw[$field] ?? null;
                    if (is_string($v) && preg_match('/^[a-f0-9-]{32,36}$/i', $v)) {
                        $explicit[] = ['id' => strtolower($v), 'show' => strtolower((string) $row->whatnot_show_id)];
                    }
                }
            }
            if (!$explicit) {
                $stats['order_payload_no_live_id']++;
                $status = 'no explicit live id';
            } else {
                $stats['order_payload_explicit_live_id']++;
                $ids = array_unique(array_column($explicit, 'id'));
                $allMatch = count($ids) === 1 && collect($explicit)->contains(fn ($e) => $e['id'] === $e['show']);
                $stats[$allMatch ? 'order_payload_live_id_matches_attached_show' : 'order_payload_live_id_conflict']++;
                $status = $allMatch ? 'payload live id matches show' : 'payload live id conflicts/ambiguous';
            }
            if (count($samples) < $examples) {
                $samples[] = [$orderId, $status, $showIds->take(8)->implode(','), $rows->count()];
            }
        }
        $this->table(['Metric', 'Orders'], collect($stats)->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->table(['Order ID', 'Payload evidence', 'Attached show IDs', 'Order rows'], $samples);
        $this->warn('READ ONLY. A show association or a matching payload field is not independently verified Whatnot ownership. No ledger, show, order or payroll records changed.');
        return self::SUCCESS;
    }
}
