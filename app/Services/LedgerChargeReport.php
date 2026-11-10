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

    /** One page of charge lines. */
    public function lines(string $from, string $until, ?int $streamerId, ?string $category, int $page, int $perPage): Collection
    {
        return $this->query($from, $until, $streamerId, $category)
            ->orderByDesc('le.created_date')->orderByDesc('le.id')
            ->forPage($page, $perPage)
            ->get(['le.id', 'le.created_date', 'le.transaction_type', 'le.message', 'le.whatnot_order_id', 'le.amount',
                'a.category', 'a.reason', 'a.streamer_id', 's.name as streamer', 'sh.title as show_title', 'sh.show_date', 'c.name as channel']);
    }

    public function count(string $from, string $until, ?int $streamerId, ?string $category): int
    {
        return $this->query($from, $until, $streamerId, $category)->count();
    }
}
