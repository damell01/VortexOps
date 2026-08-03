<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\PalletLine;
use App\Models\ProductIdentity;
use App\Models\ReceivingSession;
use App\Models\Vendor;
use App\Support\AdminModules;
use App\Support\ChannelContext;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivingAnalytics extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'purchasing';
    protected static ?string $title = 'Receiving Analytics';
    protected static ?string $navigationLabel = 'Analytics';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
    }

    public function getView(): string
    {
        return 'filament.pages.receiving-analytics';
    }

    public function getSubheading(): ?string
    {
        return 'How receiving is going — speed, accuracy, and a scorecard of your top vendors.';
    }

    /** Exports the top-vendors table — the closest thing here to a per-vendor scorecard. */
    public function exportCsv(): StreamedResponse
    {
        $vendors  = $this->topVendors;
        $filename = 'receiving-top-vendors-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($vendors) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Vendor', 'Sessions', 'Lines', 'Auto-Match %']);
            foreach ($vendors as $v) {
                fputcsv($out, [$v['vendor'], $v['sessions'], $v['lines'], $v['auto_pct']]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ── Top-level metrics ──────────────────────────────────────────────────────

    private function cacheKey(string $section): string
    {
        return "receiving_analytics_{$section}_" . auth()->id() . '_ch' . (ChannelContext::currentId() ?? 'all');
    }

    /**
     * Session-level stats (sessions, lines, vendors) are NOT channel-scoped:
     * a ReceivingSession's lines can land in inventory_locations across
     * multiple channels, so a session can't be correctly attributed to a
     * single active channel. Only receiving_cost is scoped here, since it's
     * computed from inventory_lots which each resolve to one location.
     */
    public function getSummaryProperty(): array
    {
        return Cache::remember($this->cacheKey('summary'), 60, function () {
            try {
                $completed = ReceivingSession::where('status', 'completed');

                $totalSessions  = $completed->count();
                $totalLines     = (clone $completed)->sum('total_lines');
                $autoMatched    = (clone $completed)->sum('auto_matched_count');
                $autoRate       = $totalLines > 0 ? round($autoMatched / $totalLines * 100, 1) : 0.0;

                $totalAliases   = ProductIdentity::count();
                $confirmedAlias = ProductIdentity::where('times_confirmed', '>', 0)->count();

                // Left-joined so lots without a pallet_line (synthetic/manual
                // lots) still count toward the all-channels total; they're
                // simply unattributable to any single channel when scoped.
                $totalCost = DB::table('inventory_lots')
                    ->leftJoin('pallet_lines', 'pallet_lines.id', '=', 'inventory_lots.pallet_line_id')
                    ->leftJoin('inventory_locations', 'inventory_locations.id', '=', 'pallet_lines.inventory_location_id')
                    ->where('inventory_lots.source', 'received')
                    ->when(ChannelContext::isScoped(), fn ($q) => $q->where('inventory_locations.whatnot_channel_id', ChannelContext::currentId()))
                    ->selectRaw('SUM(inventory_lots.quantity * inventory_lots.unit_cost) as total')
                    ->value('total') ?? 0;

                return [
                    'sessions'        => $totalSessions,
                    'total_lines'     => number_format($totalLines),
                    'auto_match_rate' => $autoRate,
                    'total_aliases'   => $totalAliases,
                    'confirmed'       => $confirmedAlias,
                    'receiving_cost'  => number_format($totalCost, 2),
                ];
            } catch (\Throwable) {
                return [
                    'sessions' => 0, 'total_lines' => '0', 'auto_match_rate' => 0.0,
                    'total_aliases' => 0, 'confirmed' => 0, 'receiving_cost' => '0.00',
                ];
            }
        });
    }

    private function monthFormatExpr(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at) as month"
            : "DATE_FORMAT(created_at, '%Y-%m') as month";
    }

    public function getSessionsByMonthProperty(): array
    {
        return Cache::remember($this->cacheKey('sessions_by_month'), 60, function () {
            try {
                return ReceivingSession::selectRaw(
                        $this->monthFormatExpr() . ",
                         COUNT(*) as count,
                         SUM(total_lines) as lines,
                         AVG(CASE WHEN total_lines > 0 THEN auto_matched_count / total_lines * 100 ELSE 0 END) as auto_pct"
                    )
                    ->where('status', 'completed')
                    ->groupBy('month')
                    ->orderByDesc('month')
                    ->limit(12)
                    ->get()
                    ->map(fn ($r) => [
                        'month'    => $r->month,
                        'sessions' => $r->count,
                        'lines'    => $r->lines,
                        'auto_pct' => round((float) $r->auto_pct, 1),
                    ])->reverse()->values()->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function getTopVendorsProperty(): array
    {
        return Cache::remember($this->cacheKey('top_vendors'), 60, function () {
            try {
                return ReceivingSession::selectRaw(
                        'vendor_id,
                         COUNT(*) as session_count,
                         SUM(total_lines) as lines,
                         AVG(CASE WHEN total_lines > 0 THEN auto_matched_count / total_lines * 100 ELSE 0 END) as auto_pct'
                    )
                    ->with('vendor:id,name')
                    ->whereNotNull('vendor_id')
                    ->where('status', 'completed')
                    ->groupBy('vendor_id')
                    ->orderByDesc('session_count')
                    ->limit(10)
                    ->get()
                    ->map(fn ($r) => [
                        'vendor'    => $r->vendor?->name ?? 'Unknown',
                        'sessions'  => $r->session_count,
                        'lines'     => $r->lines,
                        'auto_pct'  => round((float) $r->auto_pct, 1),
                    ])->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /** Scoped by channel via the line's inventory_location — unmapped lines drop out when a channel is active. */
    public function getMatchStageBreakdownProperty(): array
    {
        return Cache::remember($this->cacheKey('match_stage'), 60, function () {
            try {
                return PalletLine::selectRaw('match_stage, COUNT(*) as cnt')
                    ->whereNotNull('match_stage')
                    ->whereNotNull('inventory_item_id')
                    ->when(ChannelContext::isScoped(), fn ($q) => $q->whereHas(
                        'inventoryLocation',
                        fn ($l) => $l->where('whatnot_channel_id', ChannelContext::currentId())
                    ))
                    ->groupBy('match_stage')
                    ->orderByDesc('cnt')
                    ->get()
                    ->map(fn ($r) => [
                        'stage' => $r->match_stage,
                        'count' => $r->cnt,
                    ])->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    public function getAliasesLearnedByMonthProperty(): array
    {
        return Cache::remember($this->cacheKey('aliases_by_month'), 60, function () {
            try {
                return ProductIdentity::selectRaw($this->monthFormatExpr() . ", COUNT(*) as cnt")
                    ->groupBy('month')
                    ->orderByDesc('month')
                    ->limit(12)
                    ->get()
                    ->reverse()
                    ->values()
                    ->map(fn ($r) => ['month' => $r->month, 'aliases' => $r->cnt])
                    ->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
