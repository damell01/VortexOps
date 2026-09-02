<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FulfillmentResource;
use App\Models\Show;
use App\Models\StreamerLogItem;
use Filament\Widgets\Widget;

class FulfillmentCenterOverviewWidget extends Widget
{
    protected static ?int $sort = -20;
    protected int|string|array $columnSpan = 'full';
    protected static bool $isLazy = true;
    protected string $view = 'filament.widgets.fulfillment-center-overview';

    public static function canView(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner() || $user?->isFulfillment() || $user?->isFulfillmentAdmin()) ?? false;
    }

    protected function getViewData(): array
    {
        $shows = FulfillmentResource::getEloquentQuery()->limit(60)->get();

        $queue = $shows->map(function (Show $show) {
            $items = $show->streamerLogEntry?->items ?? collect();
            $pendingLines = $items->filter(fn (StreamerLogItem $item) => ! $item->isFulfillmentReviewed())->count();
            $issues = $items->where('fulfillment_status', StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();
            $shipments = (int) ($show->shipments_count ?? 0);
            $open = (int) ($show->open_shipments_count ?? 0);
            $delivered = (int) ($show->delivered_shipments_count ?? 0);
            $assigned = $show->fulfillmentUsers->isNotEmpty();

            if (! $assigned) {
                $stage = 'unassigned';
                $label = 'Needs Assignment';
                $tone = 'warning';
                $next = 'Assign fulfillment';
            } elseif ($pendingLines > 0) {
                $stage = 'review';
                $label = 'Review Logged Items';
                $tone = 'primary';
                $next = "Review {$pendingLines} logged item" . ($pendingLines === 1 ? '' : 's');
            } elseif ($issues > 0) {
                $stage = 'issues';
                $label = 'Item Issues';
                $tone = 'danger';
                $next = "Resolve {$issues} not-fulfilled item" . ($issues === 1 ? '' : 's');
            } elseif ($show->streamerLogEntry?->needsFulfillmentReview()) {
                $stage = 'verify';
                $label = 'Verify Counts';
                $tone = 'warning';
                $next = 'Verify PWE / label counts for payroll';
            } else {
                $stage = 'complete';
                $label = 'Fulfillment Complete';
                $tone = 'success';
                $next = 'Ready for payroll / closeout';
            }

            $show->setAttribute('fulfillment_stage', [
                'key' => $stage,
                'label' => $label,
                'tone' => $tone,
                'next' => $next,
                'pending_lines' => $pendingLines,
                'issues' => $issues,
                'logged_items' => $items->count(),
                'shipments' => $shipments,
                'open' => $open,
                'delivered' => $delivered,
            ]);

            return $show;
        });

        return [
            'queue' => $queue->take(12),
            'stats' => [
                'shows' => $queue->count(),
                'unassigned' => $queue->where('fulfillment_stage.key', 'unassigned')->count(),
                'review' => $queue->where('fulfillment_stage.key', 'review')->count(),
                'issues' => $queue->where('fulfillment_stage.key', 'issues')->count(),
                'verify' => $queue->where('fulfillment_stage.key', 'verify')->count(),
                'complete' => $queue->where('fulfillment_stage.key', 'complete')->count(),
                'open_shipments' => $queue->sum(fn (Show $show) => (int) $show->getAttribute('fulfillment_stage')['open']),
                'pending_lines' => $queue->sum(fn (Show $show) => (int) $show->getAttribute('fulfillment_stage')['pending_lines']),
            ],
        ];
    }
}
