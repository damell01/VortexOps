<?php

namespace App\Filament\Pages;

class ReportsOverview extends ProfitLoss
{
    protected static ?string $title = 'Reports Overview';
    protected static ?string $slug = 'reports-overview';
    public static function getNavigationSort(): ?int { return -1; }
    public function getView(): string { return 'filament.pages.reports-overview'; }
    public function getSubheading(): ?string { return 'Business performance and reporting in one place.'; }

    public function destinations(): array
    {
        return array_values(array_filter([
            Reports::canAccess() ? ['Reports & Analytics', 'Sales, channels and revenue trends', Reports::getUrl(), 'heroicon-o-chart-bar'] : null,
            WhatnotLosses::canAccess() ? ['Whatnot Losses', 'Negative estimated Whatnot earnings', WhatnotLosses::getUrl(), 'heroicon-o-exclamation-triangle'] : null,
            ProfitLoss::canAccess() ? ['Business Profitability', 'Recorded costs and worker payouts per show', ProfitLoss::getUrl(), 'heroicon-o-banknotes'] : null,
            StreamerAnalytics::canAccess() ? ['Streamer Analytics', 'Show volume and streamer performance', StreamerAnalytics::getUrl(), 'heroicon-o-users'] : null,
            InventoryReport::canAccess() ? ['Inventory Reports', 'Stock value, movement and aging', InventoryReport::getUrl(), 'heroicon-o-cube'] : null,
            \App\Filament\Resources\WhatnotLedgerResource::canViewAny() ? ['Whatnot Ledger', 'Credits, debits and transaction status', \App\Filament\Resources\WhatnotLedgerResource::getUrl('index'), 'heroicon-o-document-text'] : null,
        ]));
    }
}
