<?php

namespace App\Filament\Pages;

use App\Models\PalletLine;
use App\Models\ProductIdentity;
use App\Models\ReceivingSession;
use App\Models\Vendor;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ReceivingAnalytics extends Page
{
    protected static ?string $title = 'Receiving Analytics';
    protected static ?string $navigationLabel = 'Analytics';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('purchasing');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) && AdminModules::isEnabled('purchasing');
    }

    public function getView(): string
    {
        return 'filament.pages.receiving-analytics';
    }

    // ── Top-level metrics ──────────────────────────────────────────────────────

    public function getSummaryProperty(): array
    {
        $completed = ReceivingSession::where('status', 'completed');

        $totalSessions  = $completed->count();
        $totalLines     = (clone $completed)->sum('total_lines');
        $autoMatched    = (clone $completed)->sum('auto_matched_count');
        $autoRate       = $totalLines > 0 ? round($autoMatched / $totalLines * 100, 1) : 0.0;

        $totalAliases   = ProductIdentity::count();
        $confirmedAlias = ProductIdentity::where('times_confirmed', '>', 0)->count();

        $totalCost = DB::table('inventory_lots')
            ->where('source', 'received')
            ->selectRaw('SUM(quantity * unit_cost) as total')
            ->value('total') ?? 0;

        return [
            'sessions'        => $totalSessions,
            'total_lines'     => number_format($totalLines),
            'auto_match_rate' => $autoRate,
            'total_aliases'   => $totalAliases,
            'confirmed'       => $confirmedAlias,
            'receiving_cost'  => number_format($totalCost, 2),
        ];
    }

    public function getSessionsByMonthProperty(): array
    {
        $fmt = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at) as month"
            : "DATE_FORMAT(created_at, '%Y-%m') as month";

        return ReceivingSession::selectRaw(
                "$fmt,
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
    }

    public function getTopVendorsProperty(): array
    {
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
    }

    public function getMatchStageBreakdownProperty(): array
    {
        return PalletLine::selectRaw('match_stage, COUNT(*) as cnt')
            ->whereNotNull('match_stage')
            ->whereNotNull('inventory_item_id')
            ->groupBy('match_stage')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($r) => [
                'stage' => $r->match_stage,
                'count' => $r->cnt,
            ])->toArray();
    }

    public function getAliasesLearnedByMonthProperty(): array
    {
        $fmt = DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at) as month"
            : "DATE_FORMAT(created_at, '%Y-%m') as month";

        return ProductIdentity::selectRaw("$fmt, COUNT(*) as cnt")
            ->groupBy('month')
            ->orderByDesc('month')
            ->limit(12)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($r) => ['month' => $r->month, 'aliases' => $r->cnt])
            ->toArray();
    }
}
