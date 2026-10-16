<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Payout;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Support\BusinessTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * A pay run as a PDF: each person with their pay lines and shows, the totals,
 * and the business-account transfer. A copy is kept when a run is finalized
 * and again when it is marked paid, so there is always a record of what was
 * paid; the download button renders the run as it stands now.
 */
class PayRunReport
{
    public const STAGES = ['finalized', 'paid'];

    /** @return array<string, mixed> */
    public function build(WeeklyPayoutBatch $run): array
    {
        $payouts = Payout::where('weekly_payout_batch_id', $run->id)
            ->with(['streamer:id,name,business_id', 'show:id,title,show_date'])
            ->orderBy('streamer_id')->get();
        $types = Streamer::payoutTypeLabels();

        $people = $payouts->groupBy('streamer_id')->map(function ($lines) use ($types) {
            $streamer = $lines->first()->streamer;
            $sum = fn (callable $where) => round((float) $lines->filter($where)->sum('calculated_payout'), 2);
            $main = $lines->reject(fn ($l) => in_array($l->payout_type, ['show_hours', 'show_tips'], true));

            return [
                'name' => $streamer?->name ?? 'Unknown',
                'pay_type' => $main->pluck('payout_type')->unique()->map(fn ($t) => $types[$t] ?? str($t)->headline())->implode(', ') ?: 'Hourly',
                'shows' => $lines->pluck('show_id')->filter()->unique()->count(),
                'hours' => round((float) $lines->sum('hours_worked'), 2),
                'envelopes' => (int) $lines->sum('pwe_count'),
                'packages' => (int) $lines->sum('label_count'),
                'main' => $sum(fn ($l) => ! in_array($l->payout_type, ['show_hours', 'show_tips'], true)),
                'wages' => $sum(fn ($l) => $l->payout_type === 'show_hours'),
                'tips' => $sum(fn ($l) => $l->payout_type === 'show_tips'),
                'total' => $sum(fn () => true),
                'show_lines' => $lines->groupBy('show_id')->map(fn ($s) => [
                    'title' => $s->first()->show?->title ?? 'Show #'.$s->first()->show_id,
                    'date' => $s->first()->show?->show_date?->format('M j'),
                    'sort' => $s->first()->show?->show_date?->format('Y-m-d') ?? '',
                    'amount' => round((float) $s->sum('calculated_payout'), 2),
                ])->sortBy('sort')->values()->all(),
            ];
        })->sortByDesc('total')->values();

        $totals = [
            'people' => $people->count(),
            'shows' => $payouts->pluck('show_id')->filter()->unique()->count(),
            'hours' => round((float) $people->sum('hours'), 2),
            'main' => round((float) $people->sum('main'), 2),
            'wages' => round((float) $people->sum('wages'), 2),
            'tips' => round((float) $people->sum('tips'), 2),
            'total' => round((float) $people->sum('total'), 2),
        ];

        return [
            'run' => $run,
            'name' => $this->name($run),
            'business' => $run->business_id ? Business::whereKey($run->business_id)->value('name') : null,
            'dates' => $run->week_start?->format('M j').' – '.$run->week_end?->format('M j, Y'),
            'status' => str($run->status)->replace('_', ' ')->headline(),
            'finalized_by' => $run->finalized_by ? User::whereKey($run->finalized_by)->value('name') : null,
            'people' => $people,
            'totals' => $totals,
            'transfer' => BusinessTransfer::forRun($run),
            'generated_at' => now(\App\Support\BusinessTime::zone()),
        ];
    }

    public function name(WeeklyPayoutBatch $run): string
    {
        return $run->pay_run_type_name ?: 'Pay run #'.$run->id;
    }

    public function pdf(WeeklyPayoutBatch $run): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.pay-run', $this->build($run))->setPaper('a4', 'portrait');
    }

    public function filename(WeeklyPayoutBatch $run, ?string $stage = null): string
    {
        return 'pay-run-'.$run->id.'-'.str($this->name($run))->slug().'-'.$run->week_start?->format('Y-m-d').($stage ? "-{$stage}" : '').'.pdf';
    }

    public function path(WeeklyPayoutBatch $run, string $stage): string
    {
        return "pay-runs/{$run->id}/{$stage}.pdf";
    }

    /** Keep a copy of the run as it is now, for the record. Never stops payroll if it fails. */
    public function keep(WeeklyPayoutBatch $run, string $stage): void
    {
        rescue(fn () => Storage::disk('local')->put($this->path($run, $stage), $this->pdf($run->fresh())->output()));
    }

    /** @return array<string, int> stage => when it was saved (unix time), for the copies that exist */
    public function kept(WeeklyPayoutBatch $run): array
    {
        $disk = Storage::disk('local');

        return collect(self::STAGES)
            ->filter(fn ($stage) => $disk->exists($this->path($run, $stage)))
            ->mapWithKeys(fn ($stage) => [$stage => $disk->lastModified($this->path($run, $stage))])
            ->all();
    }
}
