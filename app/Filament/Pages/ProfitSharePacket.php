<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Pages\Page;
use App\Filament\Concerns\HasAdminNavVisibility;

class ProfitSharePacket extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'Profit Share Packet';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Finance';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-gift';
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function canAccess(): bool
    {
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();

        return AdminModules::isEnabled('payouts')
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && (bool) $user?->isAdmin();
    }

    public function getView(): string
    {
        return 'filament.pages.profit-share-packet';
    }

    public function getSubheading(): ?string
    {
        return 'A printable packet of every profit-share streamer\'s numbers for a month — for handing off to ADP or bookkeeping.';
    }

    public string $month    = '';
    public string $dateMode = 'month';  // 'month' | 'custom'
    public string $dateFrom = '';
    public string $dateTo   = '';

    public function mount(): void
    {
        $this->month    = now()->format('Y-m');
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->endOfMonth()->toDateString();
    }

    public function getDateRangeLabelProperty(): string
    {
        [$from, $to] = $this->resolvedDateRange();
        if (! $from || ! $to) return '—';
        return \Carbon\Carbon::parse($from)->format('M j, Y') . ' – ' . \Carbon\Carbon::parse($to)->format('M j, Y');
    }

    /** @return array{string|null, string|null} */
    private function resolvedDateRange(): array
    {
        if ($this->dateMode === 'custom') {
            return [$this->dateFrom ?: null, $this->dateTo ?: null];
        }

        if (! $this->month) return [null, null];
        [$year, $mon] = explode('-', $this->month);
        return ["{$year}-{$mon}-01", date('Y-m-t', strtotime("{$year}-{$mon}-01"))];
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function getPacketDataProperty(): array
    {
        [$from, $to] = $this->resolvedDateRange();
        if (! $from || ! $to) {
            return [];
        }

        $streamers = Streamer::where('status', 'active')
            ->whereIn('payout_type', ['profit_share', 'hybrid'])
            ->orderBy('name')
            ->get(['id', 'name', 'payout_percentage']);

        if ($streamers->isEmpty()) return [];

        $ids = $streamers->pluck('id');
        $showTotals = \Illuminate\Support\Facades\DB::table('show_streamer')
            ->join('shows', 'shows.id', '=', 'show_streamer.show_id')
            ->whereIn('show_streamer.streamer_id', $ids)
            ->whereBetween('shows.show_date', [$from, $to])
            ->groupBy('show_streamer.streamer_id')
            ->selectRaw('show_streamer.streamer_id, COUNT(DISTINCT shows.id) as show_count, COALESCE(SUM(shows.gross_revenue), 0) as gross_rev')
            ->get()->keyBy('streamer_id');

        $paidTotals = Payout::query()
            ->whereIn('streamer_id', $ids)
            ->whereHas('show', fn ($q) => $q->whereBetween('show_date', [$from, $to]))
            ->where('status', 'paid')
            ->groupBy('streamer_id')
            ->selectRaw('streamer_id, SUM(calculated_payout) as total_paid')
            ->pluck('total_paid', 'streamer_id');

        $rows = [];
        foreach ($streamers as $streamer) {
            $show = $showTotals->get($streamer->id);
            $showCount = (int) ($show->show_count ?? 0);
            $grossRev = (float) ($show->gross_rev ?? 0);
            $psPct = (float) ($streamer->payout_percentage ?? 0);
            $psEarned = round($grossRev * ($psPct / 100), 2);
            $totalPaid = (float) ($paidTotals[$streamer->id] ?? 0);
            $rows[] = [
                'streamer_id' => $streamer->id, 'name' => $streamer->name,
                'shows' => $showCount, 'gross_rev' => $grossRev, 'ps_pct' => $psPct,
                'ps_earned' => $psEarned, 'paid' => $totalPaid, 'balance' => $psEarned - $totalPaid,
            ];
        }

        return $rows;
    }
}
