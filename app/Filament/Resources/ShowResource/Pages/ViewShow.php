<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\ShipmentResource;
use App\Filament\Resources\ShowResource;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Services\PayoutService;
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
            'payouts.streamer',
            'latestDeductionRequest.lines.inventoryItem',
            'streamerLogEntry',
            'orders',
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
        return [
            Action::make('calculate_payout')
                ->label('Calculate Payout')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->visible(fn () => auth()->user()?->isAdmin() && $this->record->streamers->isNotEmpty())
                ->requiresConfirmation()
                ->modalHeading('Calculate Payout')
                ->modalDescription(function (): string {
                    $base = 'Computes and saves payout records for all streamers on this show based on their configured payout type and the show revenue data.';
                    $log = $this->record->streamerLogEntry;
                    if ($log?->needsFulfillmentReview()) {
                        $base .= ' ⚠ This show has a PWE + Labels streamer whose fulfillment review is not yet complete — the payout will use an estimated PWE/label count until that review happens.';
                    }
                    return $base;
                })
                ->action(function (): void {
                    try {
                        $payouts = app(PayoutService::class)->calculateForShow($this->record);
                        $count = count($payouts);
                        Notification::make()->title('Payout calculated')
                            ->body("{$count} " . ($count === 1 ? 'streamer payout' : 'streamer payouts') . ' computed and saved.')
                            ->success()->send();
                        $this->record->load('payouts.streamer');
                        $this->refreshFormData(['payouts']);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Payout calculation failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('export_pdf')
                ->label('Export P&L PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('export.show-pl-pdf', ['show' => $this->record->id]))
                ->openUrlInNewTab(),

            Action::make('end_of_stream')
                ->label(fn () => $this->record->streamerLogEntry?->submitted_at ? 'Open Show Report' : 'End of Stream')
                ->icon('heroicon-o-camera')
                ->color('warning')
                ->visible(function (): bool {
                    $user = auth()->user();
                    if (in_array($this->record->status, ['cancelled', 'closed'])) return false;
                    if ($user?->isAdmin() || $user?->isOwner()) return true;
                    return ($user?->isStreamer() ?? false)
                        && $user->streamer
                        && $this->record->streamers->contains('id', $user->streamer->id);
                })
                ->url(fn () => \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $this->record->id]))
                ->tooltip('Open the post-show inventory report'),

            Action::make('add_items')
                ->label('Add Items')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(function (): bool {
                    $user = auth()->user();
                    if (in_array($this->record->status, ['cancelled', 'closed'])) return false;
                    if ($user?->isAdmin() || $user?->isOwner()) return true;
                    return ($user?->isStreamer() ?? false)
                        && $user->streamer
                        && $this->record->streamers->contains('id', $user->streamer->id);
                })
                ->url(fn () => ShowResource::getUrl('add-items', ['record' => $this->record])),

            ActionGroup::make([
                Action::make('inventory_breakdown')
                    ->label('Inventory Breakdown')
                    ->icon('heroicon-o-chart-bar-square')
                    ->color('gray')
                    ->url(fn () => ShowResource::getUrl('inventory', ['record' => $this->record])),

                Action::make('shipments')
                    ->label(fn () => 'Shipments (' . $this->record->shipments()->count() . ')')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->url(fn () => ShipmentResource::getUrl('index', [
                        'tableFilters[show_id][value]' => $this->record->id,
                    ])),

                Action::make('review_approval')
                    ->label('Review Approval')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('info')
                    ->visible(fn () => in_array($this->record->status, ['pending_approval', 'reconciled', 'closed']))
                    ->url(fn () => DeductionRequestResource::getUrl('index', ['tableFilters[show_id][value]' => $this->record->id])),

                Action::make('detect_streamer')
                    ->label(fn () => $this->record->streamers->isEmpty() ? 'Detect Streamer' : 'Re-detect Streamer')
                    ->icon('heroicon-o-user-circle')
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->isAdmin() && ! in_array($this->record->status, ['cancelled', 'closed']))
                    ->action(function () {
                        $suggestions = $this->record->detectStreamers();
                        if (empty($suggestions)) {
                            Notification::make()->title('No streamer detected')
                                ->body('No active streamer name was found in the show title. Assign one manually via Edit.')
                                ->warning()->send();
                        } else {
                            $names = collect($suggestions)->pluck('streamer_name')->join(', ');
                            Notification::make()->title('Streamer detected')
                                ->body("Matched: {$names}. High-confidence matches have been attached to the show.")
                                ->success()->send();
                        }
                        $this->record->load('streamers');
                        $this->refreshFormData(['streamers']);
                    }),

                Action::make('map_manually')
                    ->label('Map Items Manually')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->visible(fn () => auth()->user()?->isAdmin()
                        && $this->record->orders->isNotEmpty()
                        && ! in_array($this->record->status, ['cancelled', 'closed'])
                        && (! $this->record->latestDeductionRequest || $this->record->latestDeductionRequest->status === 'draft'))
                    ->requiresConfirmation()
                    ->modalHeading('Map Sold Items to Inventory')
                    ->modalDescription('Creates deduction lines from imported order items for legacy/manual reconciliation.')
                    ->action(function () {
                        $show = $this->record;
                        $dr = $show->latestDeductionRequest ?? DeductionRequest::create([
                            'show_id' => $show->id,
                            'streamer_id' => $show->streamers->first()?->id,
                            'status' => 'draft',
                        ]);

                        if ($show->status === 'draft') $show->update(['status' => 'pending_review']);

                        $groupKey = fn ($o) => $o->lot_number !== null
                            ? "lot:{$o->lot_number}"
                            : ($o->item_name ? 'name:' . strtolower(trim($o->item_name)) : "order:{$o->id}");

                        $label = function ($o) {
                            if ($o->lot_number === null) return $o->item_name ?: "Order #{$o->id}";
                            $generic = $o->item_name && preg_match('/^\s*(item|lot)\s*#?\s*' . preg_quote((string)$o->lot_number, '/') . '\s*$/i', $o->item_name);
                            return 'Lot #' . $o->lot_number . ($o->item_name && ! $generic ? " — {$o->item_name}" : '');
                        };

                        $existing = $dr->lines->pluck('raw_description')->map(fn ($d) => strtolower(trim($d)))->all();
                        $defaultLocation = $show->defaultInventoryLocation();
                        $created = 0;

                        foreach ($show->orders->groupBy($groupKey) as $group) {
                            $description = $label($group->first());
                            if (in_array(strtolower(trim($description)), $existing)) continue;

                            DeductionRequestLine::create([
                                'deduction_request_id' => $dr->id,
                                'raw_description' => $description,
                                'quantity_suggested' => $group->sum('quantity'),
                                'quantity_approved' => $group->sum('quantity'),
                                'unit_cost_snapshot' => 0,
                                'line_total' => 0,
                                'ai_confidence' => 'manual',
                                'inventory_location_id' => $defaultLocation?->id,
                                'ops_overridden' => false,
                            ]);
                            $created++;
                        }

                        Notification::make()->title("{$created} item line" . ($created !== 1 ? 's' : '') . ' added')->success()->send();
                        $this->redirect(DeductionRequestResource::getUrl('view', ['record' => $dr->id]));
                    }),

                Action::make('raise_deduction')
                    ->label('Raise Deduction')
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->isAdmin()
                        && $this->record->orders->isEmpty()
                        && ! in_array($this->record->status, ['cancelled', 'closed'])
                        && ! $this->record->latestDeductionRequest)
                    ->requiresConfirmation()
                    ->modalHeading('Raise a Manual Deduction Request')
                    ->action(function () {
                        $dr = DeductionRequest::create([
                            'show_id' => $this->record->id,
                            'streamer_id' => $this->record->streamers->first()?->id,
                            'status' => 'draft',
                        ]);
                        Notification::make()->title('Deduction request created')->success()->send();
                        $this->redirect(DeductionRequestResource::getUrl('index', ['tableFilters[show_id][value]' => $this->record->id]));
                    }),

                Action::make('close_show')
                    ->label('Close Show')
                    ->icon('heroicon-o-lock-closed')
                    ->color('gray')
                    ->visible(fn () => $this->record->status === 'reconciled' && auth()->user()?->isAdmin())
                    ->requiresConfirmation()
                    ->action(fn () => $this->record->update(['status' => 'closed'])),

                Action::make('cancel_show')
                    ->label('Cancel Show')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => ! in_array($this->record->status, ['closed', 'cancelled']) && auth()->user()?->isAdmin())
                    ->requiresConfirmation()
                    ->action(fn () => $this->record->update(['status' => 'cancelled'])),

                Action::make('import_items_sold')
                    ->label(fn () => $this->record->orders->count() > 0 ? 'Items Sold (' . $this->record->orders->count() . ')' : 'Import Items Sold')
                    ->icon('heroicon-o-shopping-cart')
                    ->color('gray')
                    ->visible(fn () => (bool)$this->record->detail_url && \App\Services\FeatureFlagService::enabled('whatnot_import'))
                    ->requiresConfirmation()
                    ->action(function (): void {
                        try {
                            $result = app(WhatnotScraper::class)->importShowOrders($this->record);
                            Notification::make()->title('Import complete')
                                ->body("{$result['created']} new item" . ($result['created'] !== 1 ? 's' : '') . ' imported.')
                                ->success()->send();
                            $this->record->load('orders');
                        } catch (\RuntimeException $e) {
                            Notification::make()->title('Import failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])->label('More actions')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray'),

            EditAction::make(),
        ];
    }
}
