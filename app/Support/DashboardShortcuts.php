<?php

namespace App\Support;

class DashboardShortcuts
{
    public static function forCurrentUser(): array
    {
        $user = auth()->user();
        if (! $user) return [];
        $links = $user->isStreamerAssistant() ? [
            [\App\Filament\Resources\ShowResource::class, 'All shows', 'Browse every streamer and channel.', 'heroicon-o-video-camera'],
            [\App\Filament\Pages\ShowLeaderboard::class, 'Show leaderboard', 'Top net shows this week and month.', 'heroicon-o-trophy'],
            [\App\Filament\Pages\MyPay::class, 'My pay', 'Review your paid pay runs and payment history.', 'heroicon-o-banknotes'],
            [\App\Filament\Pages\Timekeeping::class, 'My time', 'Clock in or out and review your hours.', 'heroicon-o-clock'],
            [\App\Filament\Resources\InventoryItemResource::class, 'Find inventory', 'Check available stock for a show.', 'heroicon-o-magnifying-glass'],
        ] : ($user->isStreamer() ? [
            [\App\Filament\Pages\EndOfStreamForm::class, 'Log a show', 'Open your assigned shows and complete a report.', 'heroicon-o-video-camera'],
            [\App\Filament\Resources\InventoryItemResource::class, 'Find inventory', 'Check stock you can use for your show.', 'heroicon-o-magnifying-glass'],
        ] : [
            [\App\Filament\Resources\PalletResource::class, 'Receive inventory', 'Open a delivery or resume unfinished receiving.', 'heroicon-o-inbox-arrow-down'],
            [\App\Filament\Pages\PalletReceivingHistory::class, 'Receiving history', 'Find previous deliveries, costs and discrepancies.', 'heroicon-o-clock'],
            [\App\Filament\Resources\InventoryItemResource::class, 'Find inventory', 'Search stock by name, SKU or barcode.', 'heroicon-o-magnifying-glass'],
        ]);
        if ($user->isAdmin()) $links[] = [\App\Filament\Resources\StreamerLogResource::class, 'Review show reports', 'Review submissions and requests to reopen.', 'heroicon-o-clipboard-document-check'];
        $links[] = [\App\Filament\Pages\InventoryGuide::class, 'Inventory guide', 'Step-by-step help with receiving and stock.', 'heroicon-o-book-open'];
        $result = [];
        foreach ($links as [$class, $label, $description, $icon]) {
            if (! $class::canAccess()) continue;
            if ($user->isStreamer() && $class === \App\Filament\Resources\InventoryItemResource::class
                && ! RoleAccess::grants($class)) continue;
            $result[] = compact('label', 'description', 'icon') + ['url' => $class::getUrl(parameters: $class === \App\Filament\Pages\InventoryGuide::class ? ['tab'=>'tasks'] : [])];
        }
        return $result;
    }
}
