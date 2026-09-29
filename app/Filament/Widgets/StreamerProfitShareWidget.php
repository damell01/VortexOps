<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Streamer;
use Carbon\Carbon;

class StreamerProfitShareWidget extends Widget
{
    protected string $view = 'filament.widgets.streamer-profit-share-widget';

    protected function getViewData(): array
    {
        $user = auth()->user();
        $streamer = $user?->streamer;

        if (!$streamer) {
            return [];
        }

        $now = Carbon::now();
        $currentPacket = $streamer->profitSharePackets()
            ->where('year', $now->year)
            ->where('month', $now->month)
            ->first();

        $pendingPackets = $streamer->profitSharePackets()
            ->where('status', 'submitted')
            ->count();

        $approvedThisYear = $streamer->profitSharePackets()
            ->where('status', 'approved')
            ->whereYear('approved_at', $now->year)
            ->count();

        $totalApprovedThisYear = $streamer->profitSharePackets()
            ->where('status', 'approved')
            ->whereYear('approved_at', $now->year)
            ->sum('profit_share_amount');

        return [
            'currentMonth' => $now->format('F Y'),
            'currentShare' => $currentPacket?->profit_share_amount ?? 0,
            'currentStatus' => $currentPacket?->status ?? 'draft',
            'pendingPackets' => $pendingPackets,
            'approvedThisYear' => $approvedThisYear,
            'totalApprovedThisYear' => $totalApprovedThisYear,
        ];
    }
}
