<?php

namespace App\Services\AI;

use App\AI\DTOs\AiMessage;
use App\AI\Enums\AiTask;
use App\AI\Prompts\PromptLibrary;
use App\AI\Services\AiGateway;
use App\Filament\Pages\ProductInsights;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns the same numbers already in the Friday/Wednesday ops emails into a
 * 3-4 sentence plain-English summary via AiGateway — "pacing 12% ahead,
 * driven mostly by X; two streamers are down; three products need
 * reordering" instead of a wall of figures. Runs as the Reasoning task, so
 * it gets whichever model/provider Settings has configured for
 * quality-over-speed work, not the fast/chat model.
 *
 * Best-effort throughout: returns null (numbers-only email, no narrative)
 * whenever the AI module is off, there's nothing worth summarizing, the
 * provider is unreachable, or generation fails for any reason. Never blocks
 * or breaks the underlying notification.
 */
class OpsDigestService
{
    public function __construct(
        private readonly AiGateway $gateway,
        private readonly PromptLibrary $prompts,
    ) {}

    public function generate(): ?string
    {
        if (! AdminModules::isEnabled('ai')) {
            return null;
        }

        try {
            $snapshot = $this->gatherSnapshot();

            if (! $this->hasSignal($snapshot)) {
                return null;
            }

            if (! $this->gateway->isHealthy()) {
                return null;
            }

            $response = $this->gateway->chat(
                AiTask::Reasoning,
                [AiMessage::user($this->prompts->opsDigest($snapshot))],
                ['timeout' => 30],
            );

            $text = trim($response->content);

            return $response->success && $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('OpsDigestService: generation failed — ' . $e->getMessage());

            return null;
        }
    }

    /**
     * On-demand version of generate() for an arbitrary date range (e.g. the
     * Reports page's period selector), rather than the fixed "this week"
     * window the scheduled emails use. Trending-down streamers, reorder
     * count, and pending-review count are current operational state, not
     * historical to the range, same as they are in the weekly digest.
     */
    public function generateForRange(Carbon $start, Carbon $end): ?string
    {
        if (! AdminModules::isEnabled('ai')) {
            return null;
        }

        try {
            $snapshot = $this->gatherRangeSnapshot($start, $end);

            if (! $this->hasRangeSignal($snapshot)) {
                return null;
            }

            if (! $this->gateway->isHealthy()) {
                return null;
            }

            $response = $this->gateway->chat(
                AiTask::Reasoning,
                [AiMessage::user($this->prompts->opsDigestForRange($snapshot))],
                ['timeout' => 30],
            );

            $text = trim($response->content);

            return $response->success && $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('OpsDigestService: range generation failed — ' . $e->getMessage());

            return null;
        }
    }

    /** @return array<string,mixed> */
    private function gatherRangeSnapshot(Carbon $start, Carbon $end): array
    {
        $span      = $start->diffInDays($end) ?: 1;
        $prevEnd   = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($span);
        $endBound  = $end->copy()->endOfDay()->toDateTimeString();

        $cur = DB::table('shows')
            ->whereBetween('show_date', [$start->toDateString(), $endBound])
            ->whereNotIn('status', ['cancelled'])
            ->when(ChannelContext::isScoped(), fn ($q) => $q->where('whatnot_channel_id', ChannelContext::currentId()))
            ->selectRaw('COUNT(*) as shows, COALESCE(SUM(gross_revenue), 0) as gross')
            ->first();

        $prevGross = (float) DB::table('shows')
            ->whereBetween('show_date', [$prevStart->toDateString(), $prevEnd->copy()->endOfDay()->toDateTimeString()])
            ->whereNotIn('status', ['cancelled'])
            ->when(ChannelContext::isScoped(), fn ($q) => $q->where('whatnot_channel_id', ChannelContext::currentId()))
            ->sum('gross_revenue');

        $gross    = (float) ($cur->gross ?? 0);
        $trendPct = $prevGross > 0 ? round((($gross - $prevGross) / $prevGross) * 100, 1) : null;

        $topStreamer = DB::table('shows')
            ->join('show_streamer', 'show_streamer.show_id', '=', 'shows.id')
            ->join('streamers', 'streamers.id', '=', 'show_streamer.streamer_id')
            ->where('show_streamer.is_primary', true)
            ->whereBetween('shows.show_date', [$start->toDateString(), $endBound])
            ->whereNotIn('shows.status', ['cancelled'])
            ->when(ChannelContext::isScoped(), fn ($q) => $q->where('shows.whatnot_channel_id', ChannelContext::currentId()))
            ->groupBy('streamers.id', 'streamers.name')
            ->selectRaw('streamers.name, SUM(shows.gross_revenue) as total')
            ->orderByDesc('total')
            ->first();

        $trendingDown = Streamer::where('status', 'active')
            ->inChannelContext()
            ->get()
            ->filter(fn (Streamer $s) => $s->isPerformanceTrendingDown())
            ->pluck('name')
            ->all();

        $outlierShows = Show::whereBetween('show_date', [$start->toDateString(), $endBound])
            ->whereNotIn('status', ['cancelled'])
            ->inChannelContext()
            ->with('streamers')
            ->get()
            ->filter(fn (Show $s) => $s->isRevenueOutlier())
            ->map(fn (Show $s) => $s->title ?: 'Untitled show')
            ->all();

        $pendingReview = Show::whereIn('status', ['pending_review', 'pending_approval'])
            ->inChannelContext()
            ->count();

        return [
            'label'          => $start->format('M j') . ' – ' . $end->format('M j, Y'),
            'shows'          => (int) ($cur->shows ?? 0),
            'gross'          => $gross,
            'trend_pct'      => $trendPct,
            'top_streamer'   => $topStreamer?->name,
            'trending_down'  => $trendingDown,
            'outlier_shows'  => $outlierShows,
            'reorder_count'  => $this->reorderNeededCount(),
            'pending_review' => $pendingReview,
        ];
    }

    /** @param array<string,mixed> $s */
    private function hasRangeSignal(array $s): bool
    {
        return $s['gross'] > 0
            || ! empty($s['trending_down'])
            || ! empty($s['outlier_shows'])
            || $s['reorder_count'] > 0
            || $s['pending_review'] > 0;
    }

    /** @return array<string,mixed> */
    private function gatherSnapshot(): array
    {
        $week  = Show::weekPacing();
        $month = Show::monthPacing();

        $weekStart = now()->startOfWeek()->toDateString();
        $today     = now()->toDateString();

        $topStreamer = DB::table('shows')
            ->join('show_streamer', 'show_streamer.show_id', '=', 'shows.id')
            ->join('streamers', 'streamers.id', '=', 'show_streamer.streamer_id')
            ->where('show_streamer.is_primary', true)
            ->whereBetween('shows.show_date', [$weekStart, $today])
            ->whereNotIn('shows.status', ['cancelled'])
            ->when(ChannelContext::isScoped(), fn ($q) => $q->where('shows.whatnot_channel_id', ChannelContext::currentId()))
            ->groupBy('streamers.id', 'streamers.name')
            ->selectRaw('streamers.name, SUM(shows.gross_revenue) as total')
            ->orderByDesc('total')
            ->first();

        $trendingDown = Streamer::where('status', 'active')
            ->inChannelContext()
            ->get()
            ->filter(fn (Streamer $s) => $s->isPerformanceTrendingDown())
            ->pluck('name')
            ->all();

        $outlierShows = Show::whereBetween('show_date', [$weekStart, $today])
            ->whereNotIn('status', ['cancelled'])
            ->inChannelContext()
            ->with('streamers')
            ->get()
            ->filter(fn (Show $s) => $s->isRevenueOutlier())
            ->map(fn (Show $s) => $s->title ?: 'Untitled show')
            ->all();

        $pendingReview = Show::whereIn('status', ['pending_review', 'pending_approval'])
            ->inChannelContext()
            ->count();

        return [
            'week_revenue'     => $week['this_week_revenue'],
            'week_pacing_pct'  => $week['pacing_pct'],
            'month_projected'  => $month['projected_month_total'],
            'month_pacing_pct' => $month['pacing_pct'],
            'top_streamer'     => $topStreamer?->name,
            'trending_down'    => $trendingDown,
            'outlier_shows'    => $outlierShows,
            'reorder_count'    => $this->reorderNeededCount(),
            'pending_review'   => $pendingReview,
        ];
    }

    /** @param array<string,mixed> $s */
    private function hasSignal(array $s): bool
    {
        return $s['week_revenue'] > 0
            || ! empty($s['trending_down'])
            || ! empty($s['outlier_shows'])
            || $s['reorder_count'] > 0
            || $s['pending_review'] > 0;
    }

    /**
     * Same reorder math as ProductInsights (trailing-velocity vs. lead time +
     * safety buffer), aggregated in one query rather than iterating every
     * active product.
     */
    private function reorderNeededCount(): int
    {
        $trailingCutoff = now()->subDays(ProductInsights::VELOCITY_WINDOW_DAYS)->toDateString();

        $stock = DB::table('inventory_stock')
            ->select('inventory_item_id')
            ->selectRaw('SUM(quantity) as on_hand')
            ->groupBy('inventory_item_id');

        $trailing = DB::table('whatnot_show_orders')
            ->select('inventory_item_id')
            ->selectRaw('SUM(quantity) as trailing_units_sold')
            ->whereNotNull('inventory_item_id')
            ->where('show_date', '>=', $trailingCutoff)
            ->groupBy('inventory_item_id');

        $rows = DB::table('products as p')
            ->leftJoinSub($stock, 'st', 'st.inventory_item_id', '=', 'p.id')
            ->leftJoinSub($trailing, 't', 't.inventory_item_id', '=', 'p.id')
            ->leftJoin('vendors as v', 'v.id', '=', 'p.preferred_vendor_id')
            ->whereRaw('p.is_active = 1 and p.deleted_at is null')
            ->selectRaw('COALESCE(st.on_hand, 0) as on_hand, COALESCE(t.trailing_units_sold, 0) as trailing_units_sold, v.lead_time_days as vendor_lead_time_days')
            ->get();

        $count = 0;
        foreach ($rows as $r) {
            $velocity = ((float) $r->trailing_units_sold) / ProductInsights::VELOCITY_WINDOW_DAYS;
            if ($velocity <= 0) {
                continue;
            }

            $leadTime     = (int) ($r->vendor_lead_time_days ?? ProductInsights::DEFAULT_LEAD_TIME_DAYS);
            $reorderPoint = $velocity * ($leadTime + ProductInsights::SAFETY_STOCK_DAYS);

            if ((float) $r->on_hand < $reorderPoint) {
                $count++;
            }
        }

        return $count;
    }

}
