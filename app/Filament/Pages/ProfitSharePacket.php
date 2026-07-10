<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Services\FeatureFlagService;
use Filament\Pages\Page;
use App\Filament\Concerns\HasAdminNavVisibility;

class ProfitSharePacket extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'Profit Share Packet';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Reports';
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
        $user = auth()->user();
        return FeatureFlagService::enabled('profit_share_packet')
            && ($user?->isAdmin() || $user?->isOwner());
    }

    public function getView(): string
    {
        return 'filament.pages.profit-share-packet';
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
            ->get();

        $rows = [];

        foreach ($streamers as $streamer) {
            $shows = Show::whereHas('streamers', fn ($q) => $q->where('streamer_id', $streamer->id))
                ->whereBetween('show_date', [$from, $to])
                ->get(['id', 'gross_revenue']);

            $showCount   = $shows->count();
            $grossRev    = (float) $shows->sum('gross_revenue');
            $psPct       = (float) ($streamer->payout_percentage ?? 0);
            $psEarned    = round($grossRev * ($psPct / 100), 2);

            $totalPaid = (float) Payout::where('streamer_id', $streamer->id)
                ->whereHas('show', fn ($q) => $q->whereBetween('show_date', [$from, $to]))
                ->where('status', 'paid')
                ->sum('calculated_payout');

            $balance = $psEarned - $totalPaid;

            $rows[] = [
                'streamer_id' => $streamer->id,
                'name'        => $streamer->name,
                'shows'       => $showCount,
                'gross_rev'   => $grossRev,
                'ps_pct'      => $psPct,
                'ps_earned'   => $psEarned,
                'paid'        => $totalPaid,
                'balance'     => $balance,
            ];
        }

        return $rows;
    }
}
