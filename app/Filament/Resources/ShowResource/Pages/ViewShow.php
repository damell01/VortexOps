<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Filament\Resources\DeductionRequestResource;
use App\Jobs\RunShowAiMappingJob;
use App\Models\AiTask;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Services\WhatnotScraper;
use App\Support\AdminModules;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
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
            'payouts.streamer',
            'latestDeductionRequest.lines.inventoryItem',
            'orders',
        ])->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inventory_breakdown')
                ->label('Inventory Breakdown')
                ->icon('heroicon-o-chart-bar-square')
                ->color('gray')
                ->url(fn () => ShowResource::getUrl('inventory', ['record' => $this->record])),

            Action::make('run_ai_mapping')
                ->label('Run AI Mapping')
                ->icon('heroicon-o-sparkles')
                ->color('violet')
                ->visible(fn () => $this->record->status === 'pending_review' && AdminModules::isEnabled('ai'))
                ->requiresConfirmation()
                ->modalHeading('Queue AI Mapping')
                ->modalDescription('This will queue a background job to match each sold item to your inventory catalogue using Ollama. You\'ll receive a notification when it\'s done. The show will move to Pending Approval automatically.')
                ->action(function () {
                    $task = AiTask::create([
                        'type'         => 'show_ai_mapping',
                        'status'       => 'pending',
                        'taskable_type' => \App\Models\Show::class,
                        'taskable_id'   => $this->record->id,
                        'triggered_by'  => auth()->id(),
                        'input'         => ['show_id' => $this->record->id, 'show_title' => $this->record->title],
                    ]);

                    RunShowAiMappingJob::dispatch($this->record->id, $task->id)->onQueue('ai');

                    Notification::make()
                        ->title('AI Mapping queued')
                        ->body('You\'ll be notified when the job completes.')
                        ->info()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

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
                ->visible(fn () => auth()->user()?->isAdmin()
                    && ! in_array($this->record->status, ['cancelled', 'closed'])
                )
                ->action(function () {
                    $suggestions = $this->record->detectStreamers();

                    if (empty($suggestions)) {
                        Notification::make()
                            ->title('No streamer detected')
                            ->body('No active streamer name was found in the show title. Assign one manually via Edit.')
                            ->warning()
                            ->send();
                    } else {
                        $names = collect($suggestions)->pluck('streamer_name')->join(', ');
                        Notification::make()
                            ->title('Streamer detected')
                            ->body("Matched: {$names}. High-confidence matches have been attached to the show.")
                            ->success()
                            ->send();
                    }

                    $this->record->load('streamers');
                    $this->refreshFormData(['streamers']);
                }),

            Action::make('map_manually')
                ->label('Map Items Manually')
                ->icon('heroicon-o-pencil-square')
                ->color('info')
                ->visible(fn () =>
                    auth()->user()?->isAdmin()
                    && $this->record->orders->isNotEmpty()
                    && ! in_array($this->record->status, ['cancelled', 'closed'])
                    && (
                        ! $this->record->latestDeductionRequest
                        || $this->record->latestDeductionRequest->status === 'draft'
                    )
                )
                ->requiresConfirmation()
                ->modalHeading('Map Sold Items to Inventory')
                ->modalDescription('Creates deduction lines from the imported order items. You can then assign each item to an inventory item and location on the next screen.')
                ->action(function () {
                    $show = $this->record;

                    $dr = $show->latestDeductionRequest ?? DeductionRequest::create([
                        'show_id'     => $show->id,
                        'streamer_id' => $show->streamers->first()?->id,
                        'status'      => 'draft',
                    ]);

                    if (in_array($show->status, ['draft'])) {
                        $show->update(['status' => 'pending_review']);
                    }

                    // Aggregate orders by item name and create a line per distinct item
                    $existingDescriptions = $dr->lines->pluck('raw_description')
                        ->map(fn ($d) => strtolower(trim($d)))
                        ->all();

                    $defaultLocation = $show->defaultInventoryLocation();

                    $created = 0;
                    foreach ($show->orders->filter(fn ($o) => ! empty($o->item_name))->groupBy('item_name') as $itemName => $group) {
                        if (in_array(strtolower(trim($itemName)), $existingDescriptions)) {
                            continue;
                        }
                        DeductionRequestLine::create([
                            'deduction_request_id'  => $dr->id,
                            'raw_description'       => $itemName,
                            'quantity_suggested'    => $group->sum('quantity'),
                            'quantity_approved'     => $group->sum('quantity'),
                            'unit_cost_snapshot'    => 0,
                            'line_total'            => 0,
                            'ai_confidence'         => 'manual',
                            'inventory_location_id' => $defaultLocation?->id,
                            'ops_overridden'        => false,
                        ]);
                        $created++;
                    }

                    Notification::make()
                        ->title("{$created} item line" . ($created !== 1 ? 's' : '') . ' added — assign inventory items below')
                        ->success()
                        ->send();

                    $this->redirect(DeductionRequestResource::getUrl('view', ['record' => $dr->id]));
                }),

            Action::make('raise_deduction')
                ->label('Raise Deduction')
                ->icon('heroicon-o-plus-circle')
                ->color('warning')
                ->visible(fn () => auth()->user()?->isAdmin()
                    && $this->record->orders->isEmpty()
                    && ! in_array($this->record->status, ['cancelled', 'closed'])
                    && ! $this->record->latestDeductionRequest
                )
                ->requiresConfirmation()
                ->modalHeading('Raise a Manual Deduction Request')
                ->modalDescription('This creates a blank deduction request for this show that you can fill in manually. Use this when AI mapping is not needed.')
                ->action(function () {
                    $dr = DeductionRequest::create([
                        'show_id'     => $this->record->id,
                        'streamer_id' => $this->record->streamers->first()?->id,
                        'status'      => 'draft',
                    ]);

                    if ($this->record->status === 'pending_review') {
                        $this->record->update(['status' => 'mapping']);
                    }

                    Notification::make()
                        ->title('Deduction request created')
                        ->body('Review the approval request and add line items.')
                        ->success()
                        ->send();

                    $this->redirect(DeductionRequestResource::getUrl('index', [
                        'tableFilters[show_id][value]' => $this->record->id,
                    ]));
                }),

            Action::make('close_show')
                ->label('Close Show')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->visible(fn () => $this->record->status === 'reconciled' && auth()->user()?->isAdmin())
                ->requiresConfirmation()
                ->modalHeading('Close this show?')
                ->modalDescription('Closing marks the show as fully settled. This cannot be undone.')
                ->action(fn () => $this->record->update(['status' => 'closed'])),

            Action::make('cancel_show')
                ->label('Cancel Show')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => ! in_array($this->record->status, ['closed', 'cancelled']) && auth()->user()?->isAdmin())
                ->requiresConfirmation()
                ->modalHeading('Cancel this show?')
                ->modalDescription('Cancelling a show removes it from active reporting. Payouts linked to it will need to be voided manually.')
                ->action(fn () => $this->record->update(['status' => 'cancelled'])),

            Action::make('import_items_sold')
                ->label(function () {
                    $count = $this->record->orders->count();
                    return $count > 0 ? "Items Sold ({$count})" : 'Import Items Sold';
                })
                ->icon('heroicon-o-shopping-cart')
                ->color('gray')
                ->visible(fn () => (bool) $this->record->detail_url)
                ->requiresConfirmation()
                ->modalHeading('Import Items Sold from Whatnot')
                ->modalDescription('This scrapes the order list for this show from Whatnot and may take up to 60 seconds.')
                ->action(function (): void {
                    try {
                        $result = app(WhatnotScraper::class)->importShowOrders($this->record);

                        Notification::make()
                            ->title('Import complete')
                            ->body(
                                "{$result['created']} new item" . ($result['created'] !== 1 ? 's' : '') . ' imported' .
                                ($result['skipped'] ? ", {$result['skipped']} already on file" : '') . '.'
                            )
                            ->success()
                            ->send();

                        $this->record->load('orders');
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('Import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            EditAction::make(),
        ];
    }
}
