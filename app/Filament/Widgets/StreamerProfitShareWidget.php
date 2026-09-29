<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Streamer;
use Carbon\Carbon;

class StreamerProfitShareWidget extends Widget
{
    protected string $view = 'filament.widgets.streamer-profit-share-widget';
    protected static bool $isLazy = true;

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

        $packetStats = $streamer->profitSharePackets()
            ->selectRaw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'approved' AND YEAR(approved_at) = ? THEN 1 ELSE 0 END) as approved_count", [$now->year])
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'approved' AND YEAR(approved_at) = ? THEN profit_share_amount ELSE 0 END), 0) as approved_total", [$now->year])
            ->first();

        $pendingPackets = (int) ($packetStats->pending_count ?? 0);
        $approvedThisYear = (int) ($packetStats->approved_count ?? 0);
        $totalApprovedThisYear = (float) ($packetStats->approved_total ?? 0);

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
