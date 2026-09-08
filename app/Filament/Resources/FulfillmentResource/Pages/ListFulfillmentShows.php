<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Widgets\FulfillmentCenterOverviewWidget;
use App\Filament\Widgets\WhatnotSyncStatusWidget;
use App\Models\FulfillmentPackage;
use App\Models\Show;
use App\Models\StreamerLogItem;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFulfillmentShows extends ListRecords
{
    protected static string $resource = FulfillmentResource::class;

    public function getView(): string
    {
        return 'filament.resources.fulfillment-resource.pages.list-fulfillment-shows';
    }

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

    /**
     * Compact operational cards for the top of the page. The regular Filament
     * table remains available below for deep filtering and bulk browsing.
     *
     * @return \Illuminate\Support\Collection<int, array<string,mixed>>
     */
    public function getQueueCards()
    {
        $shows = FulfillmentResource::getEloquentQuery()
            ->orderByDesc('show_date')
            ->limit(18)
            ->get();

        $packageGroups = FulfillmentPackage::query()
            ->whereIn('show_id', $shows->pluck('id'))
            ->get(['id', 'show_id', 'status', 'sealed_at'])
            ->groupBy('show_id');

        return $shows->map(function (Show $show) use ($packageGroups): array {
            $items = $show->streamerLogEntry?->items ?? collect();
            $packages = $packageGroups->get($show->id, collect());
            $totalUnits = (int) $items->sum('quantity');
            $packedUnits = (int) $items->sum('packed_quantity');
            $issues = $items->filter(fn (StreamerLogItem $item) => $item->fulfillmentStatus() === StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();
            $openBoxes = $packages->filter(fn (FulfillmentPackage $package) => ! $package->isSealed())->count();
            $completed = $show->streamerLogEntry?->fulfillment_reviewed_at !== null;

            if ($completed) {
                $stage = 'Completed';
                $tone = 'success';
                $action = 'View Completed Show';
            } elseif ($issues > 0) {
                $stage = 'Needs Attention';
                $tone = 'danger';
                $action = 'Resolve Issues';
            } elseif ($packedUnits > 0 && $packedUnits < $totalUnits) {
                $stage = 'Packing';
                $tone = 'primary';
                $action = 'Continue Packing';
            } elseif ($totalUnits > 0 && $packedUnits >= $totalUnits && $openBoxes > 0) {
                $stage = 'Seal Boxes';
                $tone = 'warning';
                $action = 'Finish Boxes';
            } else {
                $stage = 'Ready to Pack';
                $tone = 'info';
                $action = 'Start Packing';
            }

            return [
                'show' => $show,
                'stage' => $stage,
                'tone' => $tone,
                'action' => $action,
                'total_units' => $totalUnits,
                'packed_units' => $packedUnits,
                'issues' => $issues,
                'boxes' => $packages->count(),
                'open_boxes' => $openBoxes,
                'progress' => $totalUnits > 0 ? min(100, (int) round(($packedUnits / $totalUnits) * 100)) : 0,
            ];
        })->sortBy(function (array $card): int {
            return match ($card['stage']) {
                'Needs Attention' => 0,
                'Packing' => 1,
                'Seal Boxes' => 2,
                'Ready to Pack' => 3,
                default => 4,
            };
        })->values();
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
