<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\ShipmentResource;
use App\Filament\Resources\ShowResource;
use App\Services\WhatnotScraper;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewShow extends ViewRecord
{
    protected static string $resource = ShowResource::class;
    protected string $view = 'filament.resources.show-resource.pages.view-show-workspace';

    protected function resolveRecord(int|string $key): \App\Models\Show
    {
        return \App\Models\Show::with([
            'streamers', 'channel', 'fulfillmentUsers',
            'latestDeductionRequest.lines.inventoryItem',
            'streamerLogEntry.streamer', 'orders', 'shipments', 'payouts.batch',
        ])->findOrFail($key);
    }

    protected function getHeaderWidgets(): array { return []; }
    protected function getFooterWidgets(): array { return []; }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $isAdmin = (bool) ($user?->isAdmin() || $user?->isOwner());
        $isAssignedStreamer = (bool) ($user?->isStreamer() && $user->streamer && $this->record->streamers->contains('id', $user->streamer->id));

        $shipments = Action::make('shipments')
            ->label(fn () => 'Shipments (' . $this->record->shipments->count() . ')')
            ->icon('heroicon-o-truck')->color('info')
            ->url(fn () => ShipmentResource::getUrl('index', ['tableFilters[show_id][value]' => $this->record->id]));

        return [
            Action::make('show_report')
                ->label(function (): string {
                    $log = $this->record->streamerLogEntry;
                    if ($log?->status === 'changes_requested') return 'Fix Show Report';
                    if ($log?->submitted_at) return 'View Show Report';
                    if ($log?->items()->exists()) return 'Resume Show Report';
                    return 'Start Show Report';
                })
                ->icon('heroicon-o-clipboard-document-list')->color('primary')
                ->visible(fn (): bool => ! in_array($this->record->status, ['cancelled'], true) && ($isAdmin || $isAssignedStreamer))
                ->url(fn () => \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $this->record->id])),

            $shipments->visible(fn (): bool => $isAssignedStreamer && ! $isAdmin),

            ActionGroup::make([
                Action::make('shipments_admin')->label(fn () => 'Shipments (' . $this->record->shipments->count() . ')')->icon('heroicon-o-truck')
                    ->url(fn () => ShipmentResource::getUrl('index', ['tableFilters[show_id][value]' => $this->record->id])),
                Action::make('edit_show')->label('Edit Show')->icon('heroicon-o-pencil-square')->url(fn () => ShowResource::getUrl('edit', ['record' => $this->record])),
                Action::make('inventory_breakdown')->label('Inventory Breakdown')->icon('heroicon-o-chart-bar-square')->url(fn () => ShowResource::getUrl('inventory', ['record' => $this->record])),
                Action::make('review_approval')->label('Review Approval')->icon('heroicon-o-clipboard-document-check')
                    ->visible(fn (): bool => in_array($this->record->status, ['pending_approval', 'reconciled', 'closed'], true))
                    ->url(fn () => DeductionRequestResource::getUrl('index', ['tableFilters[show_id][value]' => $this->record->id])),
                Action::make('detect_streamer')->label(fn () => $this->record->streamers->isEmpty() ? 'Detect Streamer' : 'Re-detect Streamer')->icon('heroicon-o-user-circle')
                    ->visible(fn (): bool => ! in_array($this->record->status, ['cancelled', 'closed'], true))
                    ->action(function (): void {
                        $suggestions = $this->record->detectStreamers();
                        Notification::make()->title(empty($suggestions) ? 'No streamer detected' : 'Streamer detected')
                            ->body(empty($suggestions) ? null : 'Matched: ' . collect($suggestions)->pluck('streamer_name')->join(', '))
                            ->{empty($suggestions) ? 'warning' : 'success'}()->send();
                        $this->record->load('streamers');
                    }),
                Action::make('import_items_sold')->label(fn () => $this->record->orders->count() > 0 ? 'Items Sold (' . $this->record->orders->count() . ')' : 'Import Items Sold')->icon('heroicon-o-shopping-cart')
                    ->visible(fn (): bool => (bool) $this->record->detail_url && \App\Services\FeatureFlagService::enabled('whatnot_import'))
                    ->requiresConfirmation()->action(function (): void {
                        try {
                            $result = app(WhatnotScraper::class)->importShowOrders($this->record);
                            Notification::make()->title('Import complete')->body("{$result['created']} new item(s) imported.")->success()->send();
                            $this->record->load('orders');
                        } catch (\RuntimeException $exception) {
                            Notification::make()->title('Import failed')->body($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('export_pdf')->label('Export P&L PDF')->icon('heroicon-o-document-arrow-down')->url(fn () => route('export.show-pl-pdf', ['show' => $this->record->id]))->openUrlInNewTab(),
                Action::make('close_show')->label('Close Show')->icon('heroicon-o-lock-closed')->visible(fn (): bool => $this->record->status === 'reconciled')->requiresConfirmation()->action(fn () => $this->record->update(['status' => 'closed'])),
                Action::make('cancel_show')->label('Cancel Show')->icon('heroicon-o-x-circle')->color('danger')->visible(fn (): bool => ! in_array($this->record->status, ['closed', 'cancelled'], true))->requiresConfirmation()->action(fn () => $this->record->update(['status' => 'cancelled'])),
            ])->label('More')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray')->visible(fn (): bool => $isAdmin),
        ];
    }
}
