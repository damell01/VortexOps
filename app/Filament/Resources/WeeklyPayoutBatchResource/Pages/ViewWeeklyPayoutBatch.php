<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Services\AdpExportService;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewWeeklyPayoutBatch extends ViewRecord
{
    protected static string $resource = WeeklyPayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Edit Details')
                ->visible(fn () => $this->record->status === 'draft'),

            DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft'),

            Action::make('preview')
                ->label('Preview Pay Run')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->visible(fn () => $this->record->status === 'draft')
                ->modalHeading('Payout preview')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn () => view('filament.payout-preview', [
                    'preview' => app(PayoutService::class)->previewFinalization($this->record),
                ])),

            // The last point at which an unapproved report can still be looked
            // at, so it says what is outstanding rather than closing the week
            // over it quietly. Overridable, because payroll has to be able to
            // close a week a streamer never filed for — but the override is
            // deliberate and it is written onto the pay run.
            Action::make('finalize')
                ->label('Finalize Pay Run')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Finalize this pay run')
                ->modalDescription(function () {
                    $problems = app(PayoutService::class)->signOffProblems($this->record);

                    if ($problems === []) {
                        return 'Every show in this run has an approved report. Finalizing locks the payout amounts and marks all streamer payouts as approved. This cannot be undone.';
                    }

                    return count($problems) . ' report(s) are not signed off yet:'
                        . "\n\n• " . implode("\n• ", $problems)
                        . "\n\nFinalizing anyway locks the amounts as they stand and records this on the pay run. It cannot be undone.";
                })
                ->modalSubmitActionLabel(fn () => app(PayoutService::class)->signOffProblems($this->record) === []
                    ? 'Finalize'
                    : 'Finalize anyway')
                ->action(function () {
                    $problems = app(PayoutService::class)->signOffProblems($this->record);

                    app(PayoutService::class)->finalizeBatch($this->record, force: true);

                    Notification::make()
                        ->title('Pay run finalized.')
                        ->body($problems === [] ? null : count($problems) . ' report(s) were not signed off; noted on the pay run.')
                        ->success()
                        ->send();
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
