<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\ShipmentResource;
use App\Filament\Resources\ShowResource;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
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

                Action::make('map_manually')
                    ->label('Map Items Manually')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => $isAdmin
                        && $this->record->orders->isNotEmpty()
                        && ! in_array($this->record->status, ['cancelled', 'closed'], true)
                        && (! $this->record->latestDeductionRequest || $this->record->latestDeductionRequest->status === 'draft'))
                    ->requiresConfirmation()
                    ->action(function (): void {
                        $show = $this->record;
                        $request = $show->latestDeductionRequest ?? DeductionRequest::create([
                            'show_id' => $show->id,
                            'streamer_id' => $show->streamers->first()?->id,
                            'status' => 'draft',
                        ]);

                        $existing = $request->lines
                            ->pluck('raw_description')
                            ->map(fn ($description) => strtolower(trim($description)))
                            ->all();
                        $defaultLocation = $show->defaultInventoryLocation();
                        $created = 0;

                        // Group first, create second.
                        //
                        // This ran straight down the orders creating a line
                        // each, and the duplicate check read $existing — built
                        // once, before the loop, and never added to inside it.
                        // So it caught a description already on the request and
                        // never one produced moments earlier in the same run:
                        // a lot sold across four orders arrived as four lines
                        // of one, for somebody to notice and merge by hand.
                        $grouped = [];

                        foreach ($show->orders as $order) {
                            $description = $this->describeOrderLine($order);
                            $key = strtolower(trim($description));

                            if (in_array($key, $existing, true)) continue;

                            $grouped[$key] ??= ['description' => $description, 'quantity' => 0];
                            $grouped[$key]['quantity'] += max(1, (int) $order->quantity);
                        }

                        foreach ($grouped as $line) {
                            DeductionRequestLine::create([
                                'deduction_request_id' => $request->id,
                                'raw_description' => $line['description'],
                                'quantity_suggested' => $line['quantity'],
                                'quantity_approved' => $line['quantity'],
                                'unit_cost_snapshot' => 0,
                                'line_total' => 0,
                                'ai_confidence' => 'manual',
                                'inventory_location_id' => $defaultLocation?->id,
                                'ops_overridden' => false,
                            ]);
                            $created++;
                        }

                        Notification::make()->title("{$created} item line(s) added")->success()->send();
                        $this->redirect(DeductionRequestResource::getUrl('view', ['record' => $request->id]));
                    }),

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

    /**
     * How one sold order reads on a deduction line.
     *
     * Whatnot fills item_name in for every lot whether or not anyone typed a
     * name, so an unnamed lot comes back as "Item #42" — which appended to its
     * own lot number gave "Lot #42 — Item #42", the same number twice with a
     * dash between. A real name still earns its place beside the lot.
     */
    private function describeOrderLine(\App\Models\WhatnotShowOrder $order): string
    {
        $name = trim((string) $order->item_name);

        if ($order->lot_number === null) {
            return $name !== '' ? $name : "Order #{$order->id}";
        }

        $lot = 'Lot #' . $order->lot_number;

        // "Item #42", "Lot 42", "#42", "42" — a placeholder standing in for the
        // lot number rather than describing what was in it.
        $restatesLot = $name === ''
            || preg_match('/^(item|lot)?\s*#?\s*' . preg_quote((string) $order->lot_number, '/') . '$/i', $name) === 1;

        return $restatesLot ? $lot : "{$lot} — {$name}";
    }
}
