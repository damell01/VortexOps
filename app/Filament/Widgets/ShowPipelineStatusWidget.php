<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use App\Services\ShowWorkflowService;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class ShowPipelineStatusWidget extends Widget
{
    protected static ?int $sort = -20;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.show-pipeline-status';

    public ?Model $record = null;

    protected function getViewData(): array
    {
        /** @var Show $show */
        $show = $this->record;

        $show->loadMissing([
            'streamers',
            'fulfillmentUsers',
            'streamerLogEntry.items.inventoryItem',
            'streamerLogEntry.streamer',
            'shipments',
            'latestDeductionRequest.lines',
            'payouts.streamer',
            'payouts.batch',
        ]);

        $workflow = app(ShowWorkflowService::class);
        $state = $workflow->stateFor($show);
        $pnl = $show->profitAndLoss();
        $log = $show->streamerLogEntry;
        $payouts = $show->payouts;

        $reportUnits = $log ? (int) $log->items->sum('quantity') : 0;
        $unmatched = $log ? $log->items->whereNull('inventory_item_id')->count() : 0;
        $postingProblems = $log ? $log->inventoryPostingProblems() : [];
        $shipmentCount = $show->shipments->count();
        $deliveredCount = $show->shipments
            ->filter(fn ($shipment) => strtolower((string) $shipment->status) === 'delivered')
            ->count();

        return [
            'show' => $show,
            'state' => $state,
            'steps' => $workflow->steps(),
            'pnl' => $pnl,
            'log' => $log,
            'reportUnits' => $reportUnits,
            'inventoryIssues' => max($unmatched, count($postingProblems)),
            'shipmentCount' => $shipmentCount,
            'openShipmentCount' => max(0, $shipmentCount - $deliveredCount),
            'payouts' => $payouts,
            'payRun' => $payouts->first(fn ($payout) => $payout->batch !== null)?->batch,
        ];
    }
}
