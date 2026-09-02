<?php

namespace App\Livewire;

use App\Models\Show;
use App\Models\WhatnotShowOrder;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithFileUploads;

class FulfillmentDashboard extends Component
{
    use WithFileUploads;

    public Show $show;
    public string $filterStatus = 'all';
    public string $search = '';
    public array $selectedOrders = [];
    public array $uploadedLabels = [];

    public function mount(Show $show): void
    {
        $this->show = $show;
    }

    public function markAsPacked(WhatnotShowOrder $order): void
    {
        abort_unless((int) $order->show_id === (int) $this->show->id, 403);

        if (! $order->shipping_status || in_array($order->shipping_status, ['pending', 'label_created'], true)) {
            $order->update(['shipping_status' => 'packed']);
            $this->selectedOrders = array_values(array_diff($this->selectedOrders, [$order->id, (string) $order->id]));
            $this->dispatch('notify', message: 'Order marked as packed');
        }
    }

    public function bulkMarkPacked(): void
    {
        $ids = collect($this->selectedOrders)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) return;

        $count = $this->show->orders()
            ->whereIn('id', $ids)
            ->where(function (Builder $q) {
                $q->whereNull('shipping_status')
                    ->orWhereIn('shipping_status', ['', 'pending', 'label_created']);
            })
            ->update(['shipping_status' => 'packed']);

        $this->selectedOrders = [];
        $this->dispatch('notify', message: "{$count} order line(s) marked packed");
    }

    public function markNextAsPacked(): void
    {
        $order = $this->show->orders()
            ->where(function ($q) {
                $q->whereNull('shipping_status')
                    ->orWhereIn('shipping_status', ['', 'pending', 'label_created']);
            })
            ->orderByRaw('COALESCE(lot_number, 999999)')
            ->orderBy('id')
            ->first();

        if (! $order) {
            $this->dispatch('notify', message: 'No unpacked order lines remain');
            return;
        }

        $this->markAsPacked($order);
    }

    public function markAsShipped(WhatnotShowOrder $order, string $trackingNumber): void
    {
        abort_unless((int) $order->show_id === (int) $this->show->id, 403);

        $trackingNumber = trim($trackingNumber);
        if ($trackingNumber === '') return;

        $order->update([
            'shipping_status' => 'shipped',
            'tracking_number' => $trackingNumber,
        ]);

        $this->dispatch('notify', message: "Order marked as shipped with tracking: {$trackingNumber}");
    }

    public function uploadLabel(WhatnotShowOrder $order, $file): void
    {
        abort_unless((int) $order->show_id === (int) $this->show->id, 403);
        if (! $file) return;

        $path = $file->store('shipping-labels', 'public');
        $order->update(['tracking_number' => $path]);
        $this->dispatch('notify', message: 'Shipping label uploaded');
    }

    public function clearSelection(): void
    {
        $this->selectedOrders = [];
    }

    public function render()
    {
        $orders = $this->show->orders()
            ->when($this->filterStatus !== 'all', function ($q) {
                if ($this->filterStatus === 'pending') {
                    $q->where(function ($pending) {
                        $pending->whereNull('shipping_status')->orWhereIn('shipping_status', ['', 'pending']);
                    });
                } else {
                    $q->where('shipping_status', $this->filterStatus);
                }
            })
            ->when(filled($this->search), function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(function ($search) use ($term) {
                    $search->where('buyer_username', 'like', $term)
                        ->orWhere('buyer_display_name', 'like', $term)
                        ->orWhere('item_name', 'like', $term)
                        ->orWhere('whatnot_order_id', 'like', $term)
                        ->orWhere('tracking_number', 'like', $term);
                });
            })
            ->orderByRaw('COALESCE(lot_number, 999999)')
            ->orderBy('id')
            ->limit(200)
            ->get();

        $allOrders = $this->show->orders()->get(['id', 'shipping_status']);
        $pendingPackingCount = $allOrders->filter(fn ($order) => in_array($order->shipping_status, [null, '', 'pending', 'label_created'], true))->count();
        $packedCount = $allOrders->where('shipping_status', 'packed')->count();
        $shippedCount = $allOrders->where('shipping_status', 'shipped')->count();
        $deliveredOrderCount = $allOrders->where('shipping_status', 'delivered')->count();

        $shipments = $this->show->shipments()
            ->orderByDesc('created_at_whatnot')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $deliveredShipments = $shipments->filter(fn ($shipment) => strtolower((string) $shipment->status) === 'delivered')->count();
        $openShipments = max(0, $shipments->count() - $deliveredShipments);

        return view('livewire.fulfillment-dashboard', [
            'show' => $this->show,
            'orders' => $orders,
            'allOrders' => $allOrders,
            'pendingPackingCount' => $pendingPackingCount,
            'packedCount' => $packedCount,
            'shippedCount' => $shippedCount,
            'deliveredOrderCount' => $deliveredOrderCount,
            'shipments' => $shipments,
            'shipmentStats' => [
                'total' => $shipments->count(),
                'delivered' => $deliveredShipments,
                'open' => $openShipments,
                'shipping_cost' => (float) $shipments->sum('shipping_cost'),
            ],
            'statusLabels' => WhatnotShowOrder::shippingStatusLabels(),
            'assignedUsers' => $this->show->fulfillmentUsers()->get(['users.id', 'users.name']),
        ]);
    }
}
