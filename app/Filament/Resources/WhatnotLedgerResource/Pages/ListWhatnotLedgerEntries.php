<?php

namespace App\Filament\Resources\WhatnotLedgerResource\Pages;

use App\Filament\Resources\WhatnotLedgerResource;
use App\Models\WhatnotChannel;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWhatnotLedgerEntries extends ListRecords
{
    protected static string $resource = WhatnotLedgerResource::class;

    public function getSubheading(): ?string
    {
        return 'Reconcile Whatnot financial activity by channel, transaction type, status and date without leaving the reporting workspace.';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('reports')
                ->label('Reports Overview')
                ->icon('heroicon-o-chart-bar-square')
                ->color('gray')
                ->url(\App\Filament\Pages\Reports::getUrl()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }

    /**
     * Ledger users switch channels constantly while reconciling Whatnot data.
     * Keep that action one click away instead of burying it in the Filters popover.
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All Channels')
                ->icon('heroicon-m-squares-2x2'),
        ];

        WhatnotChannel::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (WhatnotChannel $channel) use (&$tabs): void {
                $tabs['channel_' . $channel->id] = Tab::make($channel->name)
                    ->icon('heroicon-m-signal')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('whatnot_channel_id', $channel->id));
            });

        $unassignedCount = \App\Models\WhatnotLedgerEntry::query()
            ->whereNull('whatnot_channel_id')
            ->count();

        if ($unassignedCount > 0) {
            $tabs['unassigned'] = Tab::make('Unassigned')
                ->badge($unassignedCount)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('whatnot_channel_id'));
        }

        return $tabs;
    }
}
