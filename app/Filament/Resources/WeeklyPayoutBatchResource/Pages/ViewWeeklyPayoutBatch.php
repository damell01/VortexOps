<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Pages\PayrollOverview;
use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Filament\Widgets\PayRunSummaryWidget;
use App\Models\WeeklyPayoutBatch;
use App\Services\AdpExportService;
use App\Services\PayRunAutomationService;
use App\Services\PayRunReadinessService;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewWeeklyPayoutBatch extends ViewRecord
{
    protected static string $resource = WeeklyPayoutBatchResource::class;

    public function getTitle(): string
    {
        $start = $this->record->week_start?->format('M j');
        $end   = $this->record->week_end?->format('M j, Y');

        return $start && $end
            ? "Pay Run · {$start}–{$end}"
            : 'Pay Run';
    }

    public function getSubheading(): ?string
    {
        $status = WeeklyPayoutBatch::statusLabels()[$this->record->status] ?? ucfirst((string) $this->record->status);
        $entries = $this->record->payouts()->count();
        $total = '$' . number_format((float) $this->record->total_payout, 2);

        return "{$status} · {$entries} payout " . ($entries === 1 ? 'entry' : 'entries') . " · {$total} total";
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PayRunSummaryWidget::class,
        ];
    }

    public function getWidgetData(): array
    {
        return ['record' => $this->record];
    }

    protected function getHeaderActions(): array
    {
        $resolveBlockers = Action::make('resolve_blockers')
            ->label('Resolve Blockers')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('warning')
            ->visible(fn () => $this->record->status === 'draft'
                && app(PayRunReadinessService::class)->problems($this->record) !== [])
            ->url(fn () => PayrollOverview::getUrl(['workflow' => 'blocked']));

        $finalize = Action::make('finalize')
            ->label('Finalize Pay Run')
            ->icon('heroicon-o-lock-closed')
            ->color('warning')
            ->visible(fn () => $this->record->status === 'draft'
                && app(PayRunReadinessService::class)->problems($this->record) === [])
            ->requiresConfirmation()
            ->modalHeading('Finalize this pay run')
            ->modalDescription('This weekly Pay Run is fully signed off and current. Finalizing locks the payout amounts and marks all payouts as approved. This cannot be undone.')
            ->modalSubmitActionLabel('Finalize')
            ->action(function () {
                $problems = app(PayRunReadinessService::class)->problems($this->record);

                if ($problems !== []) {
                    Notification::make()
                        ->title('Pay Run is not ready to finalize')
                        ->body(count($problems) . ' blocker(s) still need attention. Resolve them and recalculate the Pay Run before finalizing.')
                        ->danger()
                        ->persistent()
                        ->send();
                    return;
                }

                app(PayoutService::class)->finalizeBatch($this->record);
                $this->record->refresh();

                Notification::make()
                    ->title('Pay run finalized.')
                    ->success()
                    ->send();
            });

        $markSubmitted = Action::make('mark_submitted')
            ->label('Mark Submitted to ADP')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('info')
            ->visible(fn () => $this->record->status === 'finalized')
            ->requiresConfirmation()
            ->action(function () {
                $this->record->update(['status' => 'submitted_to_adp']);
                $this->record->refresh();
                Notification::make()->title('Marked as submitted to ADP.')->success()->send();
            });

        $markPaid = Action::make('mark_paid')
            ->label('Mark Paid')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn () => $this->record->status === 'submitted_to_adp')
            ->requiresConfirmation()
            ->action(function () {
                app(PayoutService::class)->markBatchPaid($this->record);
                Notification::make()->title('Pay run marked as paid — team balances updated.')->success()->send();
                $this->refreshFormData(['status']);
                $this->record->refresh();
            });

        $exportAdp = Action::make('export_adp')
            ->label('Export ADP CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn () => in_array($this->record->status, ['finalized', 'submitted_to_adp', 'paid'], true))
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
            });

        return [
            $resolveBlockers,
            $finalize,
            $markSubmitted,
            $markPaid,
            $exportAdp,

            ActionGroup::make([
                Action::make('recalculate')
                    ->label('Recalculate Pay Run')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => $this->record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Recalculate this draft Pay Run')
                    ->modalDescription('Re-evaluates every show in this week. Only payroll-ready shows stay in the draft; newly blocked shows are removed from the run until fixed. Finalized history is never recalculated.')
                    ->action(function () {
                        $result = app(PayRunAutomationService::class)->syncWeek($this->record->week_start);
                        $this->record->refresh();

                        $parts = [
                            $result['shows_scanned'] . ' eligible show(s)',
                            $result['payouts_attached'] . ' payout(s) added',
                        ];
                        if (($result['payouts_detached'] ?? 0) > 0) {
                            $parts[] = $result['payouts_detached'] . ' blocked payout(s) removed';
                        }
                        $parts[] = count($result['warnings']) . ' warning(s)';
                        $parts[] = 'total $' . number_format((float) $this->record->total_payout, 2);

                        Notification::make()
                            ->title('Pay Run recalculated')
                            ->body(implode(' · ', $parts))
                            ->success()
                            ->send();
                    }),

                Action::make('validate_run')
                    ->label('Validate Readiness')
                    ->icon('heroicon-o-shield-check')
                    ->visible(fn () => $this->record->status === 'draft')
                    ->modalHeading('Pay Run readiness')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalDescription(function () {
                        $problems = app(PayRunReadinessService::class)->problems($this->record);
                        return $problems === []
                            ? 'Ready to finalize — show reports, fulfillment, inventory inputs and payout calculations are signed off and current.'
                            : "Needs attention:\n\n• " . implode("\n• ", $problems);
                    }),

                Action::make('preview')
                    ->label('Preview Pay Run')
                    ->icon('heroicon-o-eye')
                    ->visible(fn () => $this->record->status === 'draft')
                    ->modalHeading('Payout preview')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn () => view('filament.payout-preview', [
                        'preview' => app(PayoutService::class)->previewFinalization($this->record),
                    ])),

                EditAction::make()
                    ->label('Edit Details')
                    ->visible(fn () => $this->record->status === 'draft'),

                DeleteAction::make()
                    ->label('Delete Draft')
                    ->visible(fn () => $this->record->status === 'draft'),
            ])
                ->label('More')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->color('gray')
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }
}
