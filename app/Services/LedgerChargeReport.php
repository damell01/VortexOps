<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ledger charges ("chargebacks") a streamer carries: Whatnot ledger
 * corrections matched to them and approved, such as shipping charges,
 * refunds and order refund shipping charges.
 *
 * Only Estill Distribution streamers are charged for these, so only their
 * lines are listed. Amounts keep Whatnot's sign: a charge is negative, a
 * reversal (a shipping charge refunded) is positive. Read-only: this report
 * never changes pay.
 */
final class LedgerChargeReport
{
    /** Ledger categories that are charged to the streamer. */
    public const CATEGORIES = ['shipping', 'refunds', 'correction'];

    public const LABELS = ['shipping' => 'Shipping charges', 'refunds' => 'Refunds', 'correction' => 'Adjustments'];

    /** The business whose streamers carry ledger charges: Estill Distribution. */
    public static function chargedBusinessId(): ?int
    {
        $id = Business::query()->where('short_name', 'Estill')->orWhere('name', 'like', 'Estill%')->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    /** Whether this streamer is charged for ledger corrections. */
    public static function isCharged(?\App\Models\Streamer $streamer): bool
    {
        $business = self::chargedBusinessId();

        return $streamer !== null && $business !== null && (int) $streamer->getRawOriginal('business_id') === $business;
    }

    /** The approved charge lines for the period, newest first (optionally one streamer's). */
    public function query(string $from, string $until, ?int $streamerId = null, ?string $category = null): \Illuminate\Database\Query\Builder
    {
        $business = self::chargedBusinessId() ?? -1;

        return DB::table('whatnot_ledger_entries as le')
            ->join('ledger_attributions as a', fn ($j) => $j->on('a.whatnot_ledger_entry_id', '=', 'le.id')->where('a.status', '=', 'approved'))
            ->join('streamers as s', 's.id', '=', 'a.streamer_id')
            ->leftJoin('shows as sh', 'sh.id', '=', 'a.show_id')
            ->leftJoin('whatnot_channels as c', 'c.id', '=', 'le.whatnot_channel_id')
            ->whereIn('a.category', $category && in_array($category, self::CATEGORIES, true) ? [$category] : self::CATEGORIES)
            ->where('s.business_id', $business)
            ->where('le.created_date', '>=', Carbon::parse($from)->startOfDay())
            ->where('le.created_date', '<', Carbon::parse($until)->startOfDay()->addDay())
            ->when($streamerId, fn ($q) => $q->where('a.streamer_id', $streamerId));
    }

    /**
     * Totals per streamer and category, plus the overall total.
     *
     * @return array{people: list<array{streamer_id:int,streamer:string,shipping:float,refunds:float,correction:float,total:float,entries:int}>, totals: array<string,float>, entries:int}
     */
    public function summary(string $from, string $until, ?int $streamerId = null): array
    {
        $rows = $this->query($from, $until, $streamerId)
            ->groupBy('a.streamer_id', 's.name', 'a.category')
            ->selectRaw('a.streamer_id, s.name, a.category, SUM(le.amount) as total, COUNT(*) as entries')
            ->get();

        $people = [];
        $totals = array_fill_keys(self::CATEGORIES, 0.0) + ['total' => 0.0];
        $count = 0;
        foreach ($rows as $row) {
            $people[$row->streamer_id] ??= ['streamer_id' => (int) $row->streamer_id, 'streamer' => $row->name]
                + array_fill_keys(self::CATEGORIES, 0.0) + ['total' => 0.0, 'entries' => 0];
            $amount = round((float) $row->total, 2);
            $people[$row->streamer_id][$row->category] += $amount;
            $people[$row->streamer_id]['total'] += $amount;
            $people[$row->streamer_id]['entries'] += (int) $row->entries;
            $totals[$row->category] += $amount;
            $totals['total'] += $amount;
            $count += (int) $row->entries;
        }
        // Most charged first (charges are negative).
        usort($people, fn ($a, $b) => $a['total'] <=> $b['total']);

        return ['people' => array_values($people), 'totals' => array_map(fn ($v) => round($v, 2), $totals), 'entries' => $count];
    }

    /** One page of charge lines, each with its order's buyer, item and shipment when known. */
    public function lines(string $from, string $until, ?int $streamerId, ?string $category, int $page, int $perPage): Collection
    {
        $lines = $this->query($from, $until, $streamerId, $category)
            ->orderByDesc('le.created_date')->orderByDesc('le.id')
            ->forPage($page, $perPage)
            ->get(['le.id', 'le.created_date', 'le.transaction_type', 'le.message', 'le.whatnot_order_id', 'le.order_hash', 'le.amount',
                'a.category', 'a.reason', 'a.streamer_id', 'a.show_id', 's.name as streamer', 'sh.title as show_title', 'sh.show_date', 'c.name as channel']);

        return $this->withOrders($lines);
    }

    /** Attach buyer, item and shipment (carrier, tracking, status) by Whatnot order number: two queries a page. */
    private function withOrders(Collection $lines): Collection
    {
        $numbers = $lines->pluck('whatnot_order_id')->filter(fn ($n) => filled($n) && $n !== '-')->map(fn ($n) => (string) $n)->unique()->values();
        $orders = $numbers->isEmpty() ? collect() : DB::table('whatnot_show_orders')->whereIn('whatnot_order_id', $numbers)
            ->get(['id', 'show_id', 'whatnot_order_id', 'buyer_username', 'buyer_display_name', 'item_name', 'lot_number', 'total_price'])
            ->groupBy('whatnot_order_id');
        $shipments = $orders->isEmpty() ? collect() : DB::table('shipments')->whereIn('whatnot_order_id', $orders->flatten(1)->pluck('id'))
            ->orderByDesc('id')->get(['whatnot_order_id', 'carrier', 'tracking_number', 'status'])->unique('whatnot_order_id')->keyBy('whatnot_order_id');

        return $lines->map(function ($line) use ($orders, $shipments) {
            $candidates = $orders->get((string) $line->whatnot_order_id, collect());
            $order = $candidates->firstWhere('show_id', $line->show_id) ?? $candidates->first();
            $shipment = $order ? $shipments->get($order->id) : null;
            $line->buyer = $order ? ($order->buyer_username ?: $order->buyer_display_name) : null;
            $line->item = $order?->item_name;
            $line->lot = $order?->lot_number;
            $line->order_total = $order?->total_price;
            $line->show_id = $line->show_id ?: $order?->show_id;
            $line->carrier = $shipment?->carrier;
            $line->tracking = $shipment?->tracking_number;
            $line->shipment_status = $shipment?->status;

            return $line;
        });
    }

    public function count(string $from, string $until, ?int $streamerId, ?string $category): int
    {
        return $this->query($from, $until, $streamerId, $category)->count();
    }
}
