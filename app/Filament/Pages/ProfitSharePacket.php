<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Services\FeatureFlagService;
use Filament\Pages\Page;

class ProfitSharePacket extends Page
{
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

    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    /**
     * @return array<array<string,mixed>>
     */
    public function getPacketDataProperty(): array
    {
        if (! $this->month) {
            return [];
        }

        [$year, $mon] = explode('-', $this->month);
        $from = "{$year}-{$mon}-01";
        $to   = date('Y-m-t', strtotime($from));

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
