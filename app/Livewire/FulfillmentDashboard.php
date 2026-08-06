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
    public string $filterStatus = 'pending';
    public array $selectedOrders = [];
    public array $uploadedLabels = [];

    public function mount(Show $show)
    {
        $this->show = $show;
    }

    public function markAsPacked(WhatnotShowOrder $order): void
    {
        if (!$order->shipping_status || $order->shipping_status === 'pending') {
            $order->update(['shipping_status' => 'packed']);
            $this->dispatch('notify', message: "Order marked as packed");
        }
    }

    public function markAsShipped(WhatnotShowOrder $order, string $trackingNumber): void
    {
        if ($trackingNumber) {
            $order->update([
                'shipping_status' => 'shipped',
                'tracking_number' => $trackingNumber,
            ]);
            $this->dispatch('notify', message: "Order marked as shipped with tracking: {$trackingNumber}");
        }
    }

    public function uploadLabel(WhatnotShowOrder $order, $file): void
    {
        if ($file) {
            $path = $file->store('shipping-labels', 'public');
            $order->update(['tracking_number' => $path]);
            $this->dispatch('notify', message: "Shipping label uploaded");
        }
    }

    public function render()
    {
        $orders = $this->show->orders()
            ->when($this->filterStatus, fn ($q) => $q->where('shipping_status', $this->filterStatus))
            ->get();

        return view('livewire.fulfillment-dashboard', [
            'show' => $this->show,
            'orders' => $orders,
            'statusLabels' => WhatnotShowOrder::shippingStatusLabels(),
        ]);
    }
}
