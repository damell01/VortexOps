<?php

namespace App\Livewire;

use App\Models\Show;
use App\Models\WhatnotShowOrder;
use Livewire\Component;
use Livewire\WithFileUploads;

class FulfillmentDashboard extends Component
{
    use WithFileUploads;

    public Show $show;
    public string $filterStatus = 'all';
    public array $selectedOrders = [];
    public array $uploadedLabels = [];

    public function mount(Show $show): void
    {
        $this->show = $show;
    }

    public function markAsPacked(WhatnotShowOrder $order): void
    {
        if (! $order->shipping_status || $order->shipping_status === 'pending' || $order->shipping_status === 'label_created') {
            $order->update(['shipping_status' => 'packed']);
            $this->dispatch('notify', message: 'Order marked as packed');
        }
    }

    public function markAsShipped(WhatnotShowOrder $order, string $trackingNumber): void
    {
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
        if (! $file) return;

        $path = $file->store('shipping-labels', 'public');
        $order->update(['tracking_number' => $path]);
        $this->dispatch('notify', message: 'Shipping label uploaded');
    }

    public function render()
    {
        $orders = $this->show->orders()
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('shipping_status', $this->filterStatus))
            ->orderByRaw('COALESCE(lot_number, 999999)')
            ->limit(200)
            ->get();

        $allOrders = $this->show->orders()->get(['id', 'shipping_status']);
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
