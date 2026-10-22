<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Pages\PayrollOverview;
use App\Filament\Resources\WeeklyPayoutBatchResource;
use App\Models\WeeklyPayoutBatch;
use App\Services\AdpExportService;
use App\Services\PayRunAutomationService;
use App\Services\PayRunReadinessService;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewWeeklyPayoutBatch extends ViewRecord
{
    protected static string $resource = WeeklyPayoutBatchResource::class;
    protected string $view = 'filament.resources.weekly-payout-batch-resource.pages.view-pay-run-workspace';

    public int $peoplePage = 1;
    public string $peopleSearch = '';
    public const PEOPLE_PER_PAGE = 8;

    public function updatedPeopleSearch(): void
    {
        $this->peoplePage = 1;
    }

    public function previousPeoplePage(): void
    {
        $this->peoplePage = max(1, $this->peoplePage - 1);
    }

    public function nextPeoplePage(int $lastPage): void
    {
        $this->peoplePage = min(max(1, $lastPage), $this->peoplePage + 1);
    }

    public function getTitle(): string
    {
        $start = $this->record->week_start?->format('M j');
        $end = $this->record->week_end?->format('M j, Y');
        $business = $this->record->business_id ? $this->record->businessLabel() : null;
        $kind = $this->record->kind ? ' · ' . $this->record->kindLabel() : '';
        return ($start && $end ? "Pay Run · {$start}–{$end}" : 'Pay Run') . ($business ? " · {$business}" : '') . $kind;
    }

    public function getSubheading(): ?string
    {
        $status = WeeklyPayoutBatch::statusLabels()[$this->record->status] ?? ucfirst((string) $this->record->status);
        $entries = $this->record->payouts()->count();
        $total = '$' . number_format((float) $this->record->total_payout, 2);
        return "{$status} · {$entries} payout " . ($entries === 1 ? 'entry' : 'entries') . " · {$total} total";
    }

    public function assistantAndTipSummary(): array
    {
        $summary = app(\App\Services\AssistantPayrollSummary::class);
        return [
            'assistants' => $summary->hours($this->record->week_start->toDateString(), $this->record->week_end->toDateString())->all(),
            'tips' => $summary->tipsByChannel($this->record->week_start->toDateString(), $this->record->week_end->toDateString()),
        ];
    }

    protected function getHeaderWidgets(): array { return []; }
    public function getWidgetData(): array { return ['record' => $this->record]; }

    protected function getHeaderActions(): array
    {
        $resolveBlockers = Action::make('resolve_blockers')->label('Resolve Blockers')->icon('heroicon-o-wrench-screwdriver')->color('warning')
            ->visible(fn () => $this->record->status === 'draft' && app(PayRunReadinessService::class)->problems($this->record) !== [])
            ->url(fn () => PayrollOverview::getUrl(['workflow' => 'blocked']));

        $finalize = Action::make('finalize')->label('Finalize Pay Run')->icon('heroicon-o-lock-closed')->color('warning')
            ->visible(fn () => $this->record->status === 'draft' && app(PayRunReadinessService::class)->problems($this->record) === [])
            ->requiresConfirmation()->modalHeading('Finalize this pay run')
            ->modalDescription('This weekly Pay Run is fully signed off and current. Finalizing locks the payout amounts and marks all payouts as approved. This cannot be undone.')
            ->modalSubmitActionLabel('Finalize')->action(function () {
                $problems = app(PayRunReadinessService::class)->problems($this->record);
                if ($problems !== []) {
                    Notification::make()->title('Pay Run is not ready to finalize')->body(count($problems) . ' blocker(s) still need attention.')->danger()->persistent()->send();
                    return;
                }
                app(PayoutService::class)->finalizeBatch($this->record);
                $this->record->refresh();
                Notification::make()->title('Pay run finalized.')->success()->send();
            });

        $override = Action::make('override_payout')
            ->label('Custom Override')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn () => $this->record->status === 'draft' && $this->record->payouts()->exists())
            ->modalHeading('Override a payout')
            ->modalDescription('Use this only for a one-off adjustment. Recalculate the Pay Run first, then apply the override so the recalculation does not replace it.')
            ->modalSubmitActionLabel('Apply Override')
            ->schema([
                Select::make('payout_id')
                    ->label('Team Member / Show')
                    ->options(fn () => $this->record->payouts()
                        ->with(['streamer', 'show'])
                        ->get()
                        ->mapWithKeys(fn ($payout) => [
                            $payout->id => ($payout->streamer?->name ?? 'Unknown')
                                . ' — ' . ($payout->show?->title ?? 'No show')
                                . ' — $' . number_format((float) $payout->calculated_payout, 2),
                        ])
                        ->toArray())
                    ->searchable()
                    ->required(),
                TextInput::make('amount')
                    ->label('Override Amount')
                    ->prefix('$')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Textarea::make('reason')
                    ->label('Reason')
                    ->rows(3)
                    ->placeholder('Why is this payout being adjusted?')
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(function (array $data): void {
                $payout = $this->record->payouts()->findOrFail($data['payout_id']);
                $before = (float) $payout->calculated_payout;
                $after = round((float) $data['amount'], 2);
                $reason = trim((string) $data['reason']);
                $auditLine = sprintf(
                    'Manual Pay Run override by %s: $%0.2f → $%0.2f. Reason: %s',
                    auth()->user()?->name ?? 'Admin',
                    $before,
                    $after,
                    $reason
                );

                $payout->update([
                    'calculated_payout' => $after,
                    'calculation_notes' => trim(($payout->calculation_notes ? $payout->calculation_notes . "\n\n" : '') . $auditLine),
                ]);

                activity('pay_run')
                    ->causedBy(auth()->user())
                    ->performedOn($payout)
                    ->withProperties([
                        'pay_run_id' => $this->record->id,
                        'previous_amount' => $before,
                        'override_amount' => $after,
                        'reason' => $reason,
                    ])
                    ->log('Payout manually overridden in Pay Run');

                $this->record->recalculateTotal();
                $this->record->refresh();

                Notification::make()
                    ->title('Payout override applied')
                    ->body('$' . number_format($before, 2) . ' → $' . number_format($after, 2) . ' · ' . $reason)
                    ->success()
                    ->send();
            });

        $tyBurden = Action::make('monthly_burden_addback')
            ->label('Burden Add-Back')
            ->icon('heroicon-o-banknotes')
            ->visible(fn () => $this->record->status === 'draft')
            ->form([
                Select::make('streamer_id')->label('Streamer')->required()->options(function () {
                    return $this->record->payouts()->with('streamer')->get()->pluck('streamer')->filter()
                        ->unique('id')->filter(function ($streamer) {
                            $settings = json_decode((string) \App\Models\Setting::get('payroll_addback_streamer_' . $streamer->id), true);
                            return (bool) ($settings['enabled'] ?? false);
                        })->mapWithKeys(fn ($streamer) => [$streamer->id => $streamer->name])->all();
                })->searchable(),
            ])
            ->modalDescription('Adds the configured positive burden allowance once to this draft run, subject to the monthly payday cap.')
            ->action(function (array $data): void {
                try {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                        $batch = WeeklyPayoutBatch::query()->lockForUpdate()->findOrFail($this->record->id);
                        if ($batch->status !== 'draft') throw new \RuntimeException('Only draft pay runs can be adjusted.');
                        $streamer = \App\Models\Streamer::query()->lockForUpdate()->findOrFail((int) $data['streamer_id']);
                        $config = json_decode((string) \App\Models\Setting::get('payroll_addback_streamer_' . $streamer->id), true) ?: [];
                        if (!($config['enabled'] ?? false)) throw new \RuntimeException('Enable the burden add-back in Individual Overrides first.');
                        $amount = round((float) ($config['amount'] ?? 0), 2);
                        $limit = (int) ($config['max_per_month'] ?? 4);
                        if ($amount <= 0 || $limit < 1) throw new \RuntimeException('Invalid burden add-back settings.');
                        $month = \Illuminate\Support\Carbon::parse($batch->week_end)->format('Y-m');
                        $marker = '[BURDEN_ADDBACK:' . $streamer->id . ':' . $month . ']';
                        $payout = $batch->payouts()->where('streamer_id', $streamer->id)->orderBy('id')->first();
                        if (!$payout) throw new \RuntimeException('Streamer is not included in this pay run.');
                        if ($batch->payouts()->where('streamer_id', $streamer->id)->where(function ($q) use ($marker, $month, $streamer) {
                            $q->where('calculation_notes', 'like', '%' . $marker . '%');
                            if ($streamer->name === 'Ty Ramirez') $q->orWhere('calculation_notes', 'like', '%[TY_BURDEN_ADDBACK:' . $month . ']%');
                        })->exists()) throw new \RuntimeException('This pay run already includes the burden add-back.');
                        $count = \App\Models\Payout::query()->where('streamer_id', $streamer->id)
                            ->where(function ($q) use ($marker, $month, $streamer) {
                                $q->where('calculation_notes', 'like', '%' . $marker . '%');
                                if ($streamer->name === 'Ty Ramirez') $q->orWhere('calculation_notes', 'like', '%[TY_BURDEN_ADDBACK:' . $month . ']%');
                            })->whereHas('batch', fn ($q) => $q->where('status', '!=', 'draft'))->count();
                        if ($count >= $limit) throw new \RuntimeException('Monthly burden add-back limit reached for ' . $month . '.');
                        $payout->update(['calculated_payout' => round((float) $payout->calculated_payout + $amount, 2),
                            'calculation_notes' => trim(($payout->calculation_notes ?? '') . "\n" . $marker . ' +$' . number_format($amount, 2) . ' monthly burden add-back')]);
                        $batch->recalculateTotal();
                        activity('pay_run')->causedBy(auth()->user())->performedOn($payout)->withProperties(['pay_run_id' => $batch->id, 'month' => $month, 'amount' => $amount])->log('Monthly burden add-back applied');
                    });
                    $this->record->refresh();
                    Notification::make()->title('Burden add-back applied')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Burden add-back not applied')->body($e->getMessage())->danger()->send();
                }
            });

        $markSubmitted = Action::make('mark_submitted')->label('Mark Submitted to ADP')->icon('heroicon-o-arrow-up-tray')->color('info')
            ->visible(fn () => $this->record->status === 'finalized')->requiresConfirmation()->action(function () {
                $this->record->update(['status' => 'submitted_to_adp']); $this->record->refresh();
                Notification::make()->title('Marked as submitted to ADP.')->success()->send();
            });

        $markPaid = Action::make('mark_paid')->label('Mark Paid')->icon('heroicon-o-check-badge')->color('success')
            ->visible(fn () => $this->record->status === 'submitted_to_adp')->requiresConfirmation()->action(function () {
                app(PayoutService::class)->markBatchPaid($this->record);
                Notification::make()->title('Pay run marked as paid — team balances updated.')->success()->send();
                // The last of this week's runs to be paid gets the big one; otherwise Pikachu cheers.
                if (\App\Support\Celebrations::allPaidFor($this->record)) \App\Support\Celebrations::fire($this, 'all_paid');
                else $this->dispatch('vx-pika-cheer', text: 'Pika pika! Everyone\'s paid!');
                $this->refreshFormData(['status']); $this->record->refresh();
            });

        $exportAdp = Action::make('export_adp')->label('Export ADP CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->visible(fn () => in_array($this->record->status, ['finalized', 'submitted_to_adp', 'paid'], true))
            ->action(function (): StreamedResponse {
                $service = app(AdpExportService::class); $csv = $service->exportBatchCsv($this->record); $filename = $service->exportFilename($this->record);
                activity('pay_run')->causedBy(auth()->user())->performedOn($this->record)->log('ADP CSV exported');
                return response()->streamDownload(fn () => print($csv), $filename, ['Content-Type' => 'text/csv']);
            });

        return [
            Action::make('view_report')->label('View Report')->icon('heroicon-o-document-text')
                ->url(fn () => route('financial-reports.pay-run', ['batch' => $this->record->id]))->openUrlInNewTab(),

            $resolveBlockers, $finalize, $override, \App\Filament\Actions\OtherPaymentAction::make($this->record), $tyBurden, $markSubmitted, $markPaid, $exportAdp,
            ActionGroup::make([
                Action::make('split_by_business')->label('Split by business')->icon('heroicon-o-building-office-2')
                    ->visible(fn () => $this->record->status === 'draft' && $this->record->business_id === null && \App\Support\BusinessContext::available()->isNotEmpty())
                    ->requiresConfirmation()->modalHeading('Split this Pay Run by business')
                    ->modalDescription('Each business gets its own Pay Run for these dates, holding only its own people, so one can be finalized and paid without the other. Pay for anyone not in a business yet is taken out until they are assigned.')
                    ->action(function () {
                        try {
                            $result = app(PayRunAutomationService::class)->splitByBusiness($this->record, \App\Support\BusinessContext::currentId());
                        } catch (\Throwable $e) {
                            Notification::make()->title('Could not split this Pay Run')->body($e->getMessage())->danger()->send();
                            return;
                        }
                        $runs = collect($result['runs'])->map(fn ($run) => '#' . $run->id . ' ' . $run->businessLabel())->implode(', ');
                        Notification::make()->title('Pay Run split by business')
                            ->body('Runs: ' . $runs . ($result['unassigned'] !== [] ? '. Not in a business yet (taken out): ' . implode(', ', $result['unassigned']) : ''))
                            ->success()->persistent()->send();
                        $this->record->refresh();
                    }),
                Action::make('recalculate')->label('Recalculate Pay Run')->icon('heroicon-o-arrow-path')->visible(fn () => $this->record->status === 'draft')->requiresConfirmation()
                    ->modalHeading('Recalculate this draft Pay Run')->modalDescription('Re-evaluates eligible pay lines in this run’s date range. Only payroll-ready shows stay in the draft; newly blocked shows are removed until fixed. Apply any Custom Override after recalculating.')
                    ->action(function () {
                        $result = app(PayRunAutomationService::class)->syncWeek($this->record->week_start, true, $this->record->business_id, $this->record->kind); $this->record->refresh();
                        $parts = [$result['shows_scanned'] . ' eligible show(s)', $result['payouts_attached'] . ' payout(s) added'];
                        if (($result['payouts_detached'] ?? 0) > 0) $parts[] = $result['payouts_detached'] . ' blocked payout(s) removed';
                        $parts[] = count($result['warnings']) . ' warning(s)'; $parts[] = 'total $' . number_format((float)$this->record->total_payout, 2);
                        Notification::make()->title('Pay Run recalculated')->body(implode(' · ', $parts))->success()->send();
                    }),
                Action::make('validate_run')->label('Validate Readiness')->icon('heroicon-o-shield-check')->visible(fn () => $this->record->status === 'draft')->modalHeading('Pay Run readiness')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->modalDescription(function () { $p = app(PayRunReadinessService::class)->problems($this->record); return $p === [] ? 'Ready to finalize.' : "Needs attention:\n\n• " . implode("\n• ", $p); }),
                Action::make('preview')->label('Preview Pay Run')->icon('heroicon-o-eye')->visible(fn () => $this->record->status === 'draft')->modalHeading('Payout preview')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                    ->modalContent(fn () => view('filament.payout-preview', ['preview' => app(PayoutService::class)->previewFinalization($this->record)])),
                EditAction::make()->label('Edit Details')->visible(fn () => $this->record->status === 'draft'),
                DeleteAction::make()->label('Delete Draft')->visible(fn () => $this->record->status === 'draft'),
            ])->label('More')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray')->visible(fn () => $this->record->status === 'draft'),
        ];
    }
}
