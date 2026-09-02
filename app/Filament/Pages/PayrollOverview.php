<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Services\PayRunAutomationService;
use App\Services\PayoutService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class PayrollOverview extends Page
{
    protected static ?string $title = 'Payroll';
    protected static ?string $navigationLabel = 'Payroll';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static string|UnitEnum|null $navigationGroup = 'Payouts';
    protected static ?int $navigationSort = 1;

    public function getView(): string
    {
        return 'filament.pages.payroll-overview';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Current week, show calculations, people, exceptions and pay-run actions in one place.';
    }

    protected function getHeaderActions(): array
    {
        $run = $this->currentPayRun();

        return [
            Action::make('sync_current_week')
                ->label($run ? 'Refresh Current Week' : 'Build Current Week')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn () => ! $run || $run->status === 'draft')
                ->requiresConfirmation()
                ->modalDescription('Recalculate show payouts for the current week, attach eligible Draft entries, and refresh the weekly total. Locked payroll is never changed.')
                ->action(function (): void {
                    $result = app(PayRunAutomationService::class)->syncWeek(now()->startOfWeek());

                    Notification::make()
                        ->title($result['created'] ? 'Current pay run created' : 'Current pay run refreshed')
                        ->body($result['shows_scanned'] . ' show(s) calculated · ' . count($result['warnings']) . ' readiness warning(s).')
                        ->success()
                        ->send();
                }),

            Action::make('open_current_run')
                ->label('Open Pay Run')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn () => (bool) $this->currentPayRun())
                ->url(fn () => \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->currentPayRun()])),
        ];
    }

    public function currentPayRun(): ?WeeklyPayoutBatch
    {
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();

        return WeeklyPayoutBatch::query()
            ->withCount('payouts')
            ->where(function ($query) use ($weekStart, $weekEnd) {
                $query->whereBetween('week_start', [$weekStart, $weekEnd])
                    ->orWhereBetween('week_end', [$weekStart, $weekEnd]);
            })
            ->latest('week_start')
            ->first();
    }

    public function needsAttention(): array
    {
        $warnings = [];
        $run = $this->currentPayRun();

        $membersMissingStructure = Streamer::query()
            ->where('status', 'active')
            ->get()
            ->filter(function (Streamer $member): bool {
                try {
                    $comp = $member->effectiveCompensation();
                    return blank($comp['structure'] ?? null);
                } catch (\Throwable) {
                    return true;
                }
            })
            ->count();

        if ($membersMissingStructure > 0) {
            $warnings[] = $membersMissingStructure . ' active team member(s) need a payment structure reviewed.';
        }

        if (! $run) {
            $warnings[] = 'No pay run exists yet. Build the current week to calculate eligible shows.';
            return $warnings;
        }

        if ($run->status === 'draft') {
            foreach (app(PayoutService::class)->signOffProblems($run) as $problem) {
                $warnings[] = $problem;
            }

            $draftWithoutAmount = $run->payouts()
                ->where('status', 'draft')
                ->whereNull('calculated_payout')
                ->count();

            if ($draftWithoutAmount > 0) {
                $warnings[] = $draftWithoutAmount . ' payout entry/entries do not have a calculated amount yet.';
            }
        }

        return array_values(array_unique($warnings));
    }

    public function currentBreakdown(): array
    {
        $run = $this->currentPayRun();
        if (! $run) {
            return ['people' => 0, 'streamers' => 0, 'fulfillment' => 0, 'streamer_total' => 0.0, 'fulfillment_total' => 0.0];
        }

        $payouts = Payout::query()
            ->where('weekly_payout_batch_id', $run->id)
            ->with('streamer:id,member_type')
            ->get();

        $people = $payouts->pluck('streamer_id')->filter()->unique();
        $streamerIds = $payouts->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->pluck('streamer_id')->filter()->unique();
        $fulfillmentIds = $payouts->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->pluck('streamer_id')->filter()->unique();

        return [
            'people' => $people->count(),
            'streamers' => $streamerIds->count(),
            'fulfillment' => $fulfillmentIds->count(),
            'streamer_total' => (float) $payouts->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->sum('calculated_payout'),
            'fulfillment_total' => (float) $payouts->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->sum('calculated_payout'),
        ];
    }

    /** Weekly payroll grouped by person, with the originating show lines kept visible. */
    public function currentPeople(): Collection
    {
        $run = $this->currentPayRun();
        if (! $run) return collect();

        return Payout::query()
            ->where('weekly_payout_batch_id', $run->id)
            ->with(['streamer:id,name,member_type,payout_type', 'show:id,title,show_date'])
            ->orderBy('streamer_id')
            ->orderBy('show_id')
            ->get()
            ->groupBy('streamer_id')
            ->map(function (Collection $entries) {
                $first = $entries->first();
                $member = $first?->streamer;

                return [
                    'id' => $first?->streamer_id,
                    'name' => $member?->name ?? 'Unassigned',
                    'role' => $member?->isFulfillment() ? 'Fulfillment' : 'Streamer',
                    'payout_type' => $member?->payout_type,
                    'total' => (float) $entries->sum('calculated_payout'),
                    'base' => (float) $entries->sum(fn (Payout $p) => max(0, (float) $p->calculated_payout + (float) $p->owner_fee_deducted + (float) $p->loan_repayment_deducted)),
                    'adjustments' => (float) $entries->sum(fn (Payout $p) => -((float) $p->owner_fee_deducted + (float) $p->loan_repayment_deducted + (float) $p->shipping_surcharge_deducted)),
                    'entries' => $entries,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /** Shows that feed this week's payroll, including readiness and P&L. */
    public function currentShows(): Collection
    {
        $run = $this->currentPayRun();
        $start = $run?->week_start ?? now()->startOfWeek();
        $end = $run?->week_end ?? now()->endOfWeek();

        return Show::query()
            ->inChannelContext()
            ->whereBetween('show_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', ['cancelled'])
            ->with([
                'streamers:id,name,member_type',
                'streamerLogEntry:id,show_id,streamer_id,status,submitted_at,fulfillment_reviewed_at,approval_status,product_cost,hours_streamed,pwe_count,label_count',
                'latestDeductionRequest.lines',
                'payouts:id,show_id,streamer_id,weekly_payout_batch_id,status,calculated_payout,calculation_notes',
            ])
            ->withCount([
                'shipments as open_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
            ])
            ->orderByDesc('show_date')
            ->get()
            ->map(function (Show $show): array {
                $log = $show->streamerLogEntry;
                $payouts = $show->payouts;
                $pnl = $show->profitAndLoss();
                $paid = $payouts->isNotEmpty() && $payouts->every(fn (Payout $p) => $p->status === 'paid');
                $inRun = $payouts->contains(fn (Payout $p) => ! empty($p->weekly_payout_batch_id));

                if ($paid) {
                    [$status, $tone] = ['Paid', 'success'];
                } elseif ($inRun) {
                    [$status, $tone] = ['Included', 'primary'];
                } elseif (! $log?->submitted_at) {
                    [$status, $tone] = ['Needs Streamer Log', 'warning'];
                } elseif ($log->status !== 'admin_approved') {
                    [$status, $tone] = ['Admin Review', 'warning'];
                } elseif ((int) $show->open_shipments_count > 0 && ! $log->fulfillment_reviewed_at) {
                    [$status, $tone] = ['Fulfillment', 'info'];
                } else {
                    [$status, $tone] = ['Payroll Ready', 'success'];
                }

                return [
                    'show' => $show,
                    'streamers' => $show->streamers->pluck('name')->filter()->join(', '),
                    'sales' => (float) $show->gross_revenue,
                    'net' => (float) $show->whatnot_net + (float) $show->tips,
                    'cogs' => (float) $pnl['cogs'],
                    'gross_profit' => (float) $show->whatnot_net + (float) $show->tips - (float) $pnl['cogs'],
                    'payroll' => (float) $payouts->sum('calculated_payout'),
                    'show_net' => (float) $pnl['margin'],
                    'margin_pct' => (float) $pnl['margin_pct'],
                    'status' => $status,
                    'tone' => $tone,
                ];
            });
    }

    public function recentPayRuns(): Collection
    {
        return WeeklyPayoutBatch::query()
            ->withCount('payouts')
            ->latest('week_start')
            ->limit(6)
            ->get();
    }
}
