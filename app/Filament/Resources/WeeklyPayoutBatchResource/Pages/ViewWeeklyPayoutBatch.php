<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Services\AdpExportService;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                    app(PayoutService::class)->markBatchPaid($this->record);
                    Notification::make()->title('Pay run marked as paid — streamer balances updated.')->success()->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('export_adp')
                ->label('Export ADP CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => in_array($this->record->status, ['finalized', 'submitted_to_adp', 'paid']))
                ->action(function (): StreamedResponse {
                    $service  = app(AdpExportService::class);
                    $csv      = $service->exportBatchCsv($this->record);
                    $filename = $service->exportFilename($this->record);

                    activity('pay_run')
                        ->causedBy(auth()->user())
                        ->performedOn($this->record)
                        ->log('ADP CSV exported');

                    return response()->streamDownload(
                        fn () => print($csv),
                        $filename,
                        ['Content-Type' => 'text/csv'],
                    );
                }),
        ];
    }
}
