<?php

namespace App\Filament\Pages;

use App\Models\Payout;
use App\Models\Streamer;
use App\Models\WeeklyPayoutBatch;
use App\Services\PayoutService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class PayrollOverview extends Page
{
    protected static ?string $title = 'Payroll Overview';
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
        return 'One weekly workspace for show calculations, team pay, exceptions, approval, and history.';
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
            ->first()
            ?? WeeklyPayoutBatch::query()->withCount('payouts')->latest('week_start')->first();
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
            $warnings[] = 'No pay run exists yet. Create the weekly run when payroll is ready for review.';
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
            return ['people' => 0, 'streamers' => 0, 'fulfillment' => 0, 'shows' => 0, 'streamer_total' => 0.0, 'fulfillment_total' => 0.0];
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
            'shows' => $payouts->pluck('show_id')->filter()->unique()->count(),
            'streamer_total' => (float) $payouts->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->sum('calculated_payout'),
            'fulfillment_total' => (float) $payouts->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->sum('calculated_payout'),
        ];
    }

    public function currentPeopleRows(): Collection
    {
        $run = $this->currentPayRun();
        if (! $run) return collect();

        return Payout::query()
            ->where('weekly_payout_batch_id', $run->id)
            ->with(['streamer', 'show'])
            ->get()
            ->groupBy('streamer_id')
            ->map(function (Collection $rows) {
                $member = $rows->first()?->streamer;
                return [
                    'member' => $member,
                    'role' => $member?->isFulfillment() ? 'Fulfillment' : 'Streamer',
                    'shows' => $rows->pluck('show_id')->filter()->unique()->count(),
                    'entries' => $rows->count(),
                    'total' => (float) $rows->sum('calculated_payout'),
                    'status' => $rows->every(fn (Payout $p) => $p->status === 'paid') ? 'Paid'
                        : ($rows->every(fn (Payout $p) => in_array($p->status, ['approved', 'paid'], true)) ? 'Approved' : 'Draft'),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    public function currentShowRows(): Collection
    {
        $run = $this->currentPayRun();
        if (! $run) return collect();

        return Payout::query()
            ->where('weekly_payout_batch_id', $run->id)
            ->whereNotNull('show_id')
            ->with(['show', 'streamer'])
            ->get()
            ->groupBy('show_id')
            ->map(function (Collection $rows) {
                $show = $rows->first()?->show;
                $sales = (float) ($rows->max('gross_show_revenue') ?? $show?->gross_revenue ?? 0);
                $cogs = (float) ($rows->max('product_cost') ?? 0);
                $streamerPay = (float) $rows->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->sum('calculated_payout');
                $fulfillmentPay = (float) $rows->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->sum('calculated_payout');
                $totalPay = (float) $rows->sum('calculated_payout');

                return [
                    'show' => $show,
                    'sales' => $sales,
                    'cogs' => $cogs,
                    'gross_profit' => $sales - $cogs,
                    'streamer_pay' => $streamerPay,
                    'fulfillment_pay' => $fulfillmentPay,
                    'payroll' => $totalPay,
                    'net' => $sales - $cogs - $totalPay,
                    'status' => $rows->every(fn (Payout $p) => $p->status === 'paid') ? 'Paid'
                        : ($rows->every(fn (Payout $p) => in_array($p->status, ['approved', 'paid'], true)) ? 'Approved' : 'Draft'),
                ];
            })
            ->sortByDesc(fn ($row) => $row['show']?->show_date)
            ->values();
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
