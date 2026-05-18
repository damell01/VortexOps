<?php

namespace App\Filament\Resources\WhatnotShowResource\Pages;

use App\Filament\Resources\WhatnotShowResource;
use App\Models\Setting;
use App\Models\ShowSale;
use App\Services\OllamaService;
use App\Services\PayoutService;
use App\Services\ReconciliationService;
use App\Services\ShowService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWhatnotShow extends ViewRecord
{
    protected static string $resource = WhatnotShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('ai_match')
                ->label('AI Match Items')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->visible(fn () => Setting::getBool('ai_enabled', false))
                ->requiresConfirmation()
                ->modalHeading('AI Item Matching')
                ->modalDescription('The AI will analyse each sale item name and suggest the best matching inventory item. Existing confirmed matches will not be overwritten.')
                ->action(function () {
                    $show      = $this->record;
                    $unmatched = $show->sales()
                        ->whereNull('inventory_item_id')
                        ->get(['id', 'item_name', 'sku']);

                    if ($unmatched->isEmpty()) {
                        Notification::make()
                            ->title('All sales already have an inventory item linked.')
                            ->info()
                            ->send();
                        return;
                    }

                    $results = app(OllamaService::class)->matchSalesToInventory(
                        $unmatched->map(fn ($s) => ['item_name' => $s->item_name, 'sku' => $s->sku])->all()
                    );

                    $matched = 0;
                    foreach ($results as $suggestion) {
                        if (empty($suggestion['matched_item_id'])) continue;

                        $sale = $unmatched->firstWhere('item_name', $suggestion['sale_item_name']);
                        if (!$sale) continue;

                        ShowSale::where('id', $sale->id)->update([
                            'suggested_inventory_item_id' => $suggestion['matched_item_id'],
                            'ai_matched'                  => true,
                            'ai_confidence'               => min(100, ($suggestion['confidence'] ?? 0) * 100),
                        ]);
                        $matched++;
                    }

                    Notification::make()
                        ->title("AI matched {$matched} of {$unmatched->count()} items.")
                        ->body('Review suggestions on each sale line, then confirm by setting the Inventory Item field.')
                        ->success()
                        ->send();
                }),

            Action::make('generate_deductions')
                ->label('Generate Deduction Requests')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Creates a pending deduction request for every matched sale. Requests require admin approval before any inventory changes.')
                ->action(function () {
                    $count = app(ShowService::class)->generateDeductionRequests($this->record);

                    Notification::make()
                        ->title("{$count} deduction request(s) created.")
                        ->body('Review and approve them under Stream Tracking → Deduction Requests.')
                        ->success()
                        ->send();
                }),

            Action::make('execute_deductions')
                ->label('Execute Approved Deductions')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => auth()->user()?->isAdmin())
                ->requiresConfirmation()
                ->modalHeading('Execute Approved Deductions')
                ->modalDescription('This will deduct approved inventory from stock. This cannot be undone. Only approved requests will run — pending and rejected are skipped.')
                ->action(function () {
                    $executed = app(ReconciliationService::class)->executeApproved($this->record);

                    Notification::make()
                        ->title("{$executed} deduction(s) executed.")
                        ->success()
                        ->send();
                }),

            Action::make('calculate_payouts')
                ->label('Calculate Payouts')
                ->icon('heroicon-o-currency-dollar')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Calculates estimated payout for each streamer based on their payout type and the show financials. Results appear under Payouts.')
                ->action(function () {
                    $payouts = app(PayoutService::class)->calculateForShow($this->record);

                    Notification::make()
                        ->title(count($payouts) . ' payout(s) calculated.')
                        ->body('Review under Payouts & Pay Runs.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
