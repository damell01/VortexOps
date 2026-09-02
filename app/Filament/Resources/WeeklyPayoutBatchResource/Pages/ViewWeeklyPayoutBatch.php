<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Services\AdpExportService;
use App\Services\PayRunAutomationService;
use App\Services\PayRunReadinessService;
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

            Action::make('recalculate')
                ->label('Recalculate Pay Run')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalDescription('Refresh this Draft from the same calculation path used by automatic payroll setup. Finalized history is never recalculated.')
                ->action(function () {
                    $result = app(PayRunAutomationService::class)->syncWeek($this->record->week_start);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Pay Run recalculated')
                        ->body(count($result['warnings']) . ' readiness warning(s). Weekly total: $' . number_format((float) $this->record->total_payout, 2))
                        ->success()
                        ->send();
                }),

            Action::make('validate_run')
                ->label('Validate')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->visible(fn () => $this->record->status === 'draft')
                ->modalHeading('Pay Run readiness')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalDescription(function () {
                    $problems = app(PayRunReadinessService::class)->problems($this->record);
                    return $problems === []
                        ? 'Ready to finalize — show reports, fulfillment, inventory inputs and payout calculations are signed off.'
                        : "Needs attention:\n\n• " . implode("\n• ", $problems);
                }),

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

            Action::make('finalize')
                ->label('Finalize Pay Run')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Finalize this pay run')
                ->modalDescription(function () {
                    $problems = app(PayRunReadinessService::class)->problems($this->record);

                    if ($problems === []) {
                        return 'This weekly Pay Run is fully signed off. Finalizing locks the payout amounts and marks all payouts as approved. This cannot be undone.';
                    }

                    return 'This Pay Run cannot be finalized yet. Resolve these items first:'
                        . "\n\n• " . implode("\n• ", $problems);
                })
                ->modalSubmitActionLabel('Finalize')
                ->action(function () {
                    $problems = app(PayRunReadinessService::class)->problems($this->record);

                    if ($problems !== []) {
                        Notification::make()
                            ->title('Pay Run is not ready to finalize')
                            ->body(count($problems) . ' blocker(s) still need attention. Use Validate to review them.')
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    app(PayoutService::class)->finalizeBatch($this->record);

                    Notification::make()
                        ->title('Pay run finalized.')
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
                    Notification::make()->title('Pay run marked as paid — team balances updated.')->success()->send();
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
