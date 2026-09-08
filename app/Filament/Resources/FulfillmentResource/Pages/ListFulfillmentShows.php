<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Widgets\FulfillmentCenterOverviewWidget;
use App\Filament\Widgets\WhatnotSyncStatusWidget;
use App\Models\StreamerLogItem;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFulfillmentShows extends ListRecords
{
    protected static string $resource = FulfillmentResource::class;

    public function getTitle(): string
    {
        return 'Fulfillment Center';
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();

        return ($user?->isFulfillment() && ! $user->isAdmin())
            ? 'Work your assigned shows from ready-to-pack through sealed and complete.'
            : 'Prioritize exceptions, assign work, pack shows, seal boxes, and hand completed fulfillment into payroll.';
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'needs_attention';
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        $tabs = [
            'needs_attention' => Tab::make('Needs Attention')
                ->icon('heroicon-m-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function (Builder $q) {
                    $q->whereDoesntHave('fulfillmentUsers')
                        ->orWhereHas('streamerLogEntry.items', fn (Builder $items) => $items
                            ->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED));
                })),

            'ready' => Tab::make('Ready to Pack')
                ->icon('heroicon-m-inbox-arrow-down')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('fulfillmentUsers')
                    ->whereHas('streamerLogEntry', fn (Builder $log) => $log
                        ->whereNull('fulfillment_reviewed_at')
                        ->where(function (Builder $approved) {
                            $approved->where('status', 'admin_approved')
                                ->orWhere('approval_status', 'approved');
                        }))
                    ->whereHas('streamerLogEntry.items', fn (Builder $items) => $items
                        ->where(function (Builder $pending) {
                            $pending->whereNull('fulfillment_status')
                                ->orWhere('fulfillment_status', StreamerLogItem::FULFILLMENT_PENDING);
                        })
                        ->where(function (Builder $notStarted) {
                            $notStarted->whereNull('packed_quantity')
                                ->orWhere('packed_quantity', 0);
                        }))),

            'packing' => Tab::make('Packing')
                ->icon('heroicon-m-cube')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('streamerLogEntry', fn (Builder $log) => $log->whereNull('fulfillment_reviewed_at'))
                    ->whereHas('streamerLogEntry.items', fn (Builder $items) => $items
                        ->where('packed_quantity', '>', 0)
                        ->where(function (Builder $unfinished) {
                            $unfinished->whereNull('fulfillment_status')
                                ->orWhereIn('fulfillment_status', [StreamerLogItem::FULFILLMENT_PENDING, StreamerLogItem::FULFILLMENT_NOT_FULFILLED]);
                        }))),

            'completed' => Tab::make('Completed')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereHas('streamerLogEntry', fn (Builder $log) => $log->whereNotNull('fulfillment_reviewed_at'))
                    ->whereDoesntHave('streamerLogEntry.items', fn (Builder $items) => $items
                        ->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED))),

            'all' => Tab::make('All Shows')
                ->icon('heroicon-m-rectangle-stack'),
        ];

        if ($user) {
            $tabs = [
                'my_shows' => Tab::make('My Shows')
                    ->icon('heroicon-m-user')
                    ->modifyQueryUsing(fn (Builder $query) => $query
                        ->whereHas('fulfillmentUsers', fn (Builder $users) => $users->where('users.id', $user->id))),
                ...$tabs,
            ];
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FulfillmentCenterOverviewWidget::class,
            WhatnotSyncStatusWidget::class,
        ];
    }
}
