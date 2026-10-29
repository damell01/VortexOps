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

    public int $showsPage = 1;

    public function previousShowsPage(): void
    {
        $this->showsPage = max(1, $this->showsPage - 1);
    }

    public function nextShowsPage(): void
    {
        $this->showsPage++;
    }

    public function periodShows(): ?\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $run = $this->record;
        if ($run->status !== 'draft' || $run->kind === 'other') {
            return null;
        }

        return \App\Models\Show::query()
            ->where('show_date', '>=', $run->week_start->toDateString())
            ->where('show_date', '<', $run->week_end->copy()->addDay()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->whereHas('streamers', fn ($q) => $q->when($run->business_id, fn ($q) => $q->where('business_id', $run->business_id)))
            ->with(['streamers' => fn ($q) => $q->when($run->business_id, fn ($q) => $q->where('business_id', $run->business_id)), 'streamerLogEntry', 'channel'])
            ->orderBy('show_date')->orderBy('id')
            ->paginate(12, ['*'], 'showsPage', max(1, $this->showsPage));
    }

    public function periodShowStatus(\App\Models\Show $show): string
    {
        if ($this->record->payouts->contains('show_id', $show->id)) {
            return 'Included in pay';
        }
        if (! $show->is_operational) {
            return 'Historical — outside payroll';
        }
        $report = $show->streamerLogEntry;
        if ($report?->status === 'changes_requested') {
            return 'Changes requested';
        }
        if (! $report?->submitted_at && $report?->status !== 'admin_approved') {
            return 'Needs report';
        }
        if ($report->status !== 'admin_approved' && $report->approval_status !== 'approved') {
            return 'Needs approval';
        }
        return 'Pending payroll review';
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
            'tip_channels' => $this->tipsByChannel(),
        ];
    }

    /**
     * Ledger tips per channel over the run's dates, every business together.
     * Channels are shared, so this is a reference total, not an allocation.
     *
     * @return list<array{channel: string, net: int}>
     */
    private function tipsByChannel(): array
    {
        $report = app(\App\Services\LedgerTipReport::class)->between(
            $this->record->week_start->toDateString(),
            $this->record->week_end->toDateString(),
            'all',
            'all',
            false,
        );

        return collect($report['rows'])
            ->groupBy('channel')
            ->map(fn ($rows, $channel) => ['channel' => $channel, 'net' => (int) $rows->sum('net')])
            ->sortBy('channel')
            ->values()
            ->all();
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

        $editInputs = Action::make('edit_show_inputs')
            ->label('Edit Show Inputs')
            ->icon('heroicon-o-pencil-square')
            ->visible(fn () => $this->record->status === 'draft')
            ->modalHeading('Edit show payroll inputs')
            ->modalDescription('Edits the existing show report inputs and recalculates draft payouts for everyone on that show. Already finalized or paid shows cannot be edited. Show duration is not automatically paid hours.')
            ->fillForm(function (array $arguments): array {
                $payout = $this->record->payouts()->with('show.streamerLogEntry')->findOrFail($arguments['payout_id'] ?? 0);
                $log = $payout->show?->streamerLogEntry;
                return [
                    'payout_id' => $payout->id,
                    'hours_streamed' => $log?->hours_streamed,
                    'number_of_shipments' => $log?->number_of_shipments,
                    'label_count' => $log?->label_count,
                    'pwe_count' => $log?->pwe_count,
                    'reason' => '',
                ];
            })
            ->schema([
                TextInput::make('payout_id')->hidden()->required(),
                TextInput::make('hours_streamed')->label('Paid hours')->numeric()->minValue(0)->step(0.01)->nullable(),
                TextInput::make('number_of_shipments')->label('Shipments')->integer()->minValue(0)->nullable(),
                TextInput::make('label_count')->label('Labels')->integer()->minValue(0)->nullable(),
                TextInput::make('pwe_count')->label('PWE')->integer()->minValue(0)->nullable(),
                Textarea::make('reason')->label('Reason for adjustment')->required()->maxLength(1000)->rows(2),
            ])
            ->action(function (array $data): void {
                \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                    $run = WeeklyPayoutBatch::query()->lockForUpdate()->findOrFail($this->record->id);
                    abort_unless($run->status === 'draft', 403);
                    $payout = $run->payouts()->with('show.streamerLogEntry')->findOrFail((int) $data['payout_id']);
                    $show = $payout->show;
                    abort_unless($show !== null, 422, 'This payout has no show.');
                    $lockedPayoutExists = \App\Models\Payout::query()->where('show_id', $show->id)
                        ->whereHas('batch', fn ($query) => $query->where('status', '!=', 'draft'))->exists();
                    abort_if($lockedPayoutExists, 422, 'This show has finalized payroll; inputs are locked.');
                    $log = $show->streamerLogEntry;
                    abort_unless($log !== null, 422, 'This show has no report yet. Enter its inputs in the show report before editing them here.');
                    $fields = ['hours_streamed', 'number_of_shipments', 'label_count', 'pwe_count'];
                    $before = $log->only($fields);
                    $updates = [];
                    foreach ($fields as $field) {
                        $updates[$field] = ($data[$field] ?? null) === null || $data[$field] === '' ? null : $data[$field];
                    }
                    $log->update($updates);
                    $show->unsetRelation('streamerLogEntry');
                    $show->load(['streamerLogEntry', 'streamers', 'channel']);
                    app(PayoutService::class)->calculateForShow($show);
                    $run->recalculateTotal();
                    activity('pay_run')->causedBy(auth()->user())->performedOn($run)
                        ->withProperties(['show_id' => $show->id, 'payout_id' => $payout->id, 'before' => $before, 'after' => $updates, 'reason' => trim($data['reason'])])
                        ->log('Show payroll inputs edited from draft pay run');
                });
                $this->record->refresh();
                Notification::make()->title('Show inputs saved and draft payouts recalculated')->success()->send();
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

            $resolveBlockers, $finalize,
            \App\Filament\Actions\OtherPaymentAction::make($this->record),
            $override, $editInputs, $markSubmitted, $markPaid, $exportAdp,
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
