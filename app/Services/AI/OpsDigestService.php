<?php

namespace App\Services\AI;

use App\Filament\Pages\ProductInsights;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns the same numbers already in the Friday/Wednesday ops emails into a
 * 3-4 sentence plain-English summary via the local Ollama model — "pacing
 * 12% ahead, driven mostly by X; two streamers are down; three products need
 * reordering" instead of a wall of figures.
 *
 * Best-effort throughout: returns null (numbers-only email, no narrative)
 * whenever the AI module is off, there's nothing worth summarizing, Ollama
 * is unreachable, or generation fails for any reason. Never blocks or
 * breaks the underlying notification.
 */
class OpsDigestService
{
    public function __construct(private readonly OllamaClient $ollama) {}

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

            if (! $this->ollama->isOnline()) {
                return null;
            }

            $text = trim($this->ollama->generate($this->buildPrompt($snapshot), ['timeout' => 30]));

            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::warning('OpsDigestService: generation failed — ' . $e->getMessage());

            return null;
        }
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

    /** @param array<string,mixed> $s */
    private function buildPrompt(array $s): string
    {
        $lines = [];

        $lines[] = 'Revenue so far this week: $' . number_format($s['week_revenue'], 2)
            . ($s['week_pacing_pct'] !== null
                ? ' (' . ($s['week_pacing_pct'] >= 0 ? '+' : '') . $s['week_pacing_pct'] . '% vs. the trailing 4-week average for this point in the week)'
                : ' (no trailing average yet to compare)');

        if ($s['top_streamer']) {
            $lines[] = "Top streamer this week: {$s['top_streamer']}.";
        }

        if ($s['month_projected'] !== null) {
            $lines[] = 'Projected month total: $' . number_format($s['month_projected'], 2)
                . ($s['month_pacing_pct'] !== null
                    ? ' (' . ($s['month_pacing_pct'] >= 0 ? '+' : '') . $s['month_pacing_pct'] . '% vs. the trailing 3-month average)'
                    : '');
        }

        if (! empty($s['trending_down'])) {
            $lines[] = 'Streamers trending down vs. their own recent average: ' . implode(', ', $s['trending_down']) . '.';
        }

        if (! empty($s['outlier_shows'])) {
            $lines[] = 'Shows with unusual revenue this week (verify the numbers): ' . implode(', ', $s['outlier_shows']) . '.';
        }

        if ($s['reorder_count'] > 0) {
            $lines[] = "{$s['reorder_count']} product(s) are pacing toward running out before they can be restocked.";
        }

        if ($s['pending_review'] > 0) {
            $lines[] = "{$s['pending_review']} show(s) are still waiting on review/approval.";
        }

        $data = implode("\n", $lines);

        return <<<PROMPT
You are writing a short business briefing for the owner of a sports-card livestream sales operation. Below is this week's operational data. Write a 3-4 sentence plain-English summary highlighting what matters most — lead with the most important thing, mention specific names/numbers from the data, and end with anything that needs action. Do not invent numbers or reasons not present in the data. Do not use bullet points or headers — write flowing prose. Do not add a greeting or sign-off.

Data:
{$data}

Summary:
PROMPT;
    }
}
