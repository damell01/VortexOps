<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\InventoryCatalog;
use App\Filament\Pages\PayrollOverview;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Models\Show;
use App\Models\WeeklyPayoutBatch;
use App\Services\ShowWorkflowService;
use Filament\Widgets\Widget;

class AdminShowPipelineWidget extends Widget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';
    protected static bool $isLazy = true;
    protected string $view = 'filament.widgets.admin-show-pipeline';

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false;
    }

    protected function getViewData(): array
    {
        $workflow = app(ShowWorkflowService::class);

        $shows = Show::query()
            ->inChannelContext()
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['cancelled'])
            ->with([
                'streamers',
                'streamerLogEntry.streamer',
                'fulfillmentUsers',
                'payouts.batch',
                'latestDeductionRequest.lines',
            ])
            ->withSum('payouts', 'calculated_payout')
            ->orderByDesc('show_date')
            ->orderByDesc('start_time')
            ->limit(10)
            ->get()
            ->map(function (Show $show) use ($workflow) {
                $show->setAttribute('workflow_state', $workflow->stateFor($show));
                $show->setAttribute('pnl_summary', $show->profitAndLoss());
                return $show;
            });

        $currentPayRun = WeeklyPayoutBatch::query()
            ->withCount('payouts')
            ->whereDate('week_start', '<=', today())
            ->whereDate('week_end', '>=', today())
            ->latest('week_start')
            ->first()
            ?? WeeklyPayoutBatch::query()->withCount('payouts')->latest('week_start')->first();

        return [
            'shows' => $shows,
            'counts' => $shows->groupBy(fn (Show $show) => $show->getAttribute('workflow_state')['key'])->map->count(),
            'currentPayRun' => $currentPayRun,
            'workflowSteps' => $workflow->steps(),
            'showsUrl' => ShowResource::getUrl('index'),
            'logsUrl' => StreamerLogResource::getUrl('index'),
            'payrollUrl' => PayrollOverview::getUrl(),
            'catalogUrl' => InventoryCatalog::getUrl(),
        ];
    }
}
