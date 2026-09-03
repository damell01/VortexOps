<?php

namespace App\Filament\Widgets;

use App\Models\WeeklyPayoutBatch;
use App\Services\PayRunReadinessService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class PayRunSummaryWidget extends Widget
{
    protected static ?int $sort = -30;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.pay-run-summary';

    public ?Model $record = null;

    /** @return array<int, array<string, string>> */
    public function getStats(): array
    {
        /** @var WeeklyPayoutBatch|null $batch */
        $batch = $this->record;

        if (! $batch) {
            return [];
        }

        $entryCount = $batch->payouts()->count();
        $blockers = $batch->status === 'draft'
            ? app(PayRunReadinessService::class)->problems($batch)
            : [];

        $nextAction = match ($batch->status) {
            'draft' => $blockers === [] ? 'Finalize Pay Run' : 'Resolve Blockers',
            'finalized' => 'Submit to ADP',
            'submitted_to_adp' => 'Mark Paid',
            'paid' => 'Complete',
            default => 'Review',
        };

        return [
            [
                'label' => 'Total Payroll',
                'value' => '$' . number_format((float) $batch->total_payout, 2),
                'sub'   => number_format($entryCount) . ' payout ' . ($entryCount === 1 ? 'entry' : 'entries'),
                'icon'  => 'heroicon-o-banknotes',
                'tone'  => 'green',
            ],
            [
                'label' => 'Status',
                'value' => WeeklyPayoutBatch::statusLabels()[$batch->status] ?? ucfirst((string) $batch->status),
                'sub'   => $batch->week_end ? 'Week ending ' . $batch->week_end->format('M j, Y') : 'Weekly pay run',
                'icon'  => 'heroicon-o-calendar-days',
                'tone'  => $batch->status === 'paid' ? 'green' : 'blue',
            ],
            [
                'label' => 'Readiness',
                'value' => $batch->status !== 'draft'
                    ? 'Locked'
                    : ($blockers === [] ? 'Ready' : count($blockers) . ' blocker' . (count($blockers) === 1 ? '' : 's')),
                'sub'   => $batch->status !== 'draft'
                    ? 'Amounts are no longer recalculated'
                    : ($blockers === [] ? 'All payroll checks passed' : 'Needs attention before finalizing'),
                'icon'  => 'heroicon-o-shield-check',
                'tone'  => $blockers === [] ? 'green' : 'amber',
            ],
            [
                'label' => 'Next Action',
                'value' => $nextAction,
                'sub'   => $batch->status === 'paid' ? 'Payroll workflow complete' : 'Recommended workflow step',
                'icon'  => 'heroicon-o-arrow-right-circle',
                'tone'  => $batch->status === 'paid' ? 'green' : 'violet',
            ],
        ];
    }
}
