<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FulfillmentResource;
use App\Models\Show;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

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
        $query = FulfillmentResource::getEloquentQuery();
        $shows = (clone $query)->limit(60)->get();

        $queue = $shows->map(function (Show $show) {
            $orders = (int) ($show->orders_count ?? 0);
            $shipments = (int) ($show->shipments_count ?? 0);
            $open = (int) ($show->open_shipments_count ?? 0);
            $delivered = (int) ($show->delivered_shipments_count ?? 0);
            $assigned = $show->fulfillmentUsers->isNotEmpty();

            $pendingLines = $show->orders()
                ->where(function (Builder $q) {
                    $q->whereNull('shipping_status')
                        ->orWhereIn('shipping_status', ['', 'pending', 'label_created']);
                })
                ->count();

            if (! $assigned) {
                $stage = 'unassigned';
                $label = 'Needs Assignment';
                $tone = 'warning';
                $next = 'Assign fulfillment';
            } elseif ($pendingLines > 0) {
                $stage = 'packing';
                $label = 'Packing';
                $tone = 'primary';
                $next = "Pack {$pendingLines} line" . ($pendingLines === 1 ? '' : 's');
            } elseif ($open > 0) {
                $stage = 'shipping';
                $label = 'Shipping';
                $tone = 'info';
                $next = "Work {$open} open shipment" . ($open === 1 ? '' : 's');
            } elseif ($shipments > 0 && $open === 0) {
                $stage = 'complete';
                $label = 'Fulfillment Complete';
                $tone = 'success';
                $next = 'Ready for payroll / closeout';
            } elseif ($orders > 0) {
                $stage = 'ready';
                $label = 'Ready to Pack';
                $tone = 'primary';
                $next = 'Open show and start packing';
            } else {
                $stage = 'waiting';
                $label = 'Waiting on Data';
                $tone = 'gray';
                $next = 'Waiting for Whatnot shipment data';
            }

            $show->setAttribute('fulfillment_stage', [
                'key' => $stage,
                'label' => $label,
                'tone' => $tone,
                'next' => $next,
                'pending_lines' => $pendingLines,
                'orders' => $orders,
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
                'packing' => $queue->whereIn('fulfillment_stage.key', ['ready', 'packing'])->count(),
                'shipping' => $queue->where('fulfillment_stage.key', 'shipping')->count(),
                'complete' => $queue->where('fulfillment_stage.key', 'complete')->count(),
                'open_shipments' => $queue->sum(fn (Show $show) => (int) $show->getAttribute('fulfillment_stage')['open']),
                'pending_lines' => $queue->sum(fn (Show $show) => (int) $show->getAttribute('fulfillment_stage')['pending_lines']),
            ],
        ];
    }
}
