<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\ShipmentResource;
use App\Filament\Resources\ShowResource;
use App\Services\WhatnotScraper;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewShow extends ViewRecord
{
    protected static string $resource = ShowResource::class;

    protected function resolveRecord(int|string $key): \App\Models\Show
    {
        return \App\Models\Show::with([
            'streamers',
            'channel',
            'fulfillmentUsers',
            'latestDeductionRequest.lines.inventoryItem',
            'streamerLogEntry',
            'orders',
            'shipments',
        ])->findOrFail($key);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\ShowMetricsWidget::class,
            \App\Filament\Widgets\ShowPipelineStatusWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\ShowStreamerLogWidget::class,
            \App\Filament\Widgets\ShowActivityWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return ['record' => $this->record];
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $isAdmin = (bool) ($user?->isAdmin() || $user?->isOwner());
        $isAssignedStreamer = (bool) (
            $user?->isStreamer()
            && $user->streamer
            && $this->record->streamers->contains('id', $user->streamer->id)
        );

        return [
            Action::make('show_report')
                ->label(function (): string {
                    $log = $this->record->streamerLogEntry;
                    if ($log?->status === 'changes_requested') return 'Fix Show Report';
                    if ($log?->submitted_at) return 'View Show Report';
                    if ($log?->items()->exists()) return 'Resume Show Report';
                    return 'Start Show Report';
                })
                ->icon('heroicon-o-clipboard-document-list')
                ->color('primary')
                ->visible(fn (): bool => ! in_array($this->record->status, ['cancelled'], true) && ($isAdmin || $isAssignedStreamer))
                ->url(fn () => \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $this->record->id])),

            Action::make('shipments')
                ->label(fn () => 'Shipments (' . $this->record->shipments->count() . ')')
                ->icon('heroicon-o-truck')
                ->color('info')
                ->visible(fn (): bool => $isAdmin || $isAssignedStreamer)
                ->url(fn () => ShipmentResource::getUrl('index', [
                    'tableFilters[show_id][value]' => $this->record->id,
                ])),

            ActionGroup::make([
                Action::make('inventory_breakdown')
                    ->label('Inventory Breakdown')
                    ->icon('heroicon-o-chart-bar-square')
                    ->url(fn () => ShowResource::getUrl('inventory', ['record' => $this->record])),

                Action::make('review_approval')
                    ->label('Review Approval')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (): bool => $isAdmin && in_array($this->record->status, ['pending_approval', 'reconciled', 'closed'], true))
                    ->url(fn () => DeductionRequestResource::getUrl('index', [
                        'tableFilters[show_id][value]' => $this->record->id,
                    ])),

                Action::make('detect_streamer')
                    ->label(fn () => $this->record->streamers->isEmpty() ? 'Detect Streamer' : 'Re-detect Streamer')
                    ->icon('heroicon-o-user-circle')
                    ->visible(fn (): bool => $isAdmin && ! in_array($this->record->status, ['cancelled', 'closed'], true))
                    ->action(function (): void {
                        $suggestions = $this->record->detectStreamers();
                        if (empty($suggestions)) {
                            Notification::make()->title('No streamer detected')->warning()->send();
                        } else {
                            Notification::make()
                                ->title('Streamer detected')
                                ->body('Matched: ' . collect($suggestions)->pluck('streamer_name')->join(', '))
                                ->success()
                                ->send();
                        }
                        $this->record->load('streamers');
                    }),

                // "Map Items Manually" used to sit here. It walked the
                // Whatnot orders and wrote a deduction line per lot — "Lot
                // #42", "Lot #42 — Item #42" — which is not information
                // anybody wanted: Whatnot names a lot whether or not a human
                // did, so most lines were a number restating itself.
                //
                // It also fed nothing. Inventory is posted from the streamer's
                // End of Stream report, where the actual items are recorded by
                // the person who held them; these lines were a parallel list
                // for someone to reconcile by hand against that one. The
                // totals worth having off Whatnot — items sold, giveaways —
                // are on the metrics strip above, which is where a count
                // belongs.

                Action::make('import_items_sold')
                    ->label(fn () => $this->record->orders->count() > 0 ? 'Items Sold (' . $this->record->orders->count() . ')' : 'Import Items Sold')
                    ->icon('heroicon-o-shopping-cart')
                    ->visible(fn (): bool => $isAdmin
                        && (bool) $this->record->detail_url
                        && \App\Services\FeatureFlagService::enabled('whatnot_import'))
                    ->requiresConfirmation()
                    ->action(function (): void {
                        try {
                            $result = app(WhatnotScraper::class)->importShowOrders($this->record);
                            Notification::make()
                                ->title('Import complete')
                                ->body("{$result['created']} new item(s) imported.")
                                ->success()
                                ->send();
                            $this->record->load('orders');
                        } catch (\RuntimeException $exception) {
                            Notification::make()->title('Import failed')->body($exception->getMessage())->danger()->send();
                        }
                    }),

                Action::make('export_pdf')
                    ->label('Export P&L PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->visible(fn (): bool => $isAdmin)
                    ->url(fn () => route('export.show-pl-pdf', ['show' => $this->record->id]))
                    ->openUrlInNewTab(),

                Action::make('close_show')
                    ->label('Close Show')
                    ->icon('heroicon-o-lock-closed')
                    ->visible(fn (): bool => $isAdmin && $this->record->status === 'reconciled')
                    ->requiresConfirmation()
                    ->action(fn () => $this->record->update(['status' => 'closed'])),

                Action::make('cancel_show')
                    ->label('Cancel Show')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (): bool => $isAdmin && ! in_array($this->record->status, ['closed', 'cancelled'], true))
                    ->requiresConfirmation()
                    ->action(fn () => $this->record->update(['status' => 'cancelled'])),
            ])
                ->label('More')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->color('gray')
                ->visible(fn (): bool => $isAdmin),

            EditAction::make()->visible(fn (): bool => $isAdmin),
        ];
    }
}
