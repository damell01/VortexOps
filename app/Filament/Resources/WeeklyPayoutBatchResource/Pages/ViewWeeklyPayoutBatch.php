<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWeeklyPayoutBatch extends ViewRecord
{
    protected static string $resource = WeeklyPayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalize')
                ->label('Finalize Pay Run')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalDescription('Finalizing locks the payout amounts and marks all streamer payouts as approved. This cannot be undone.')
                ->action(function () {
                    app(PayoutService::class)->finalizeBatch($this->record);
                    Notification::make()->title('Pay run finalized.')->success()->send();
                }),

            Action::make('mark_submitted')
                ->label('Mark Submitted to ADP')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->visible(fn () => $this->record->status === 'finalized')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'submitted_to_adp']);
                    Notification::make()->title('Marked as submitted to ADP.')->success()->send();
                }),

            Action::make('mark_paid')
                ->label('Mark Paid')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => $this->record->status === 'submitted_to_adp')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'paid']);
                    $this->record->payouts()->update(['status' => 'paid']);
                    Notification::make()->title('Pay run marked as paid.')->success()->send();
                }),
        ];
    }
}
