<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\WhatnotShowOrder;
use Filament\Resources\Pages\Page;

class ShippingDesk extends Page
{
    protected static string $resource = FulfillmentResource::class;
    protected static bool $shouldRegisterNavigation = false;

    public int $record;
    public string $search = '';
    public string $statusFilter = 'all';

    public function getView(): string
    {
        return 'filament.resources.fulfillment-resource.pages.shipping-desk';
    }

    public function mount(int|string $record): void
    {
        $show = FulfillmentResource::getEloquentQuery()->findOrFail($record);
        abort_unless(FulfillmentResource::canView($show), 403);
        $this->record = (int) $show->id;
    }

    public function getTitle(): string
    {
        return 'Shipping & Labels';
    }

    public function shippingData(): array
    {
        $show = FulfillmentResource::getEloquentQuery()->findOrFail($this->record);
        abort_unless(FulfillmentResource::canView($show), 403);
        $orders = WhatnotShowOrder::query()->where('show_id', $show->id)
            ->orderBy('buyer_username')->orderBy('id')->get();
        $shipments = Shipment::query()->where('show_id', $show->id)
            ->orderBy('buyer_username')->orderBy('id')->get();
        $groups = $orders->groupBy(fn ($order) => mb_strtolower(trim((string) ($order->buyer_username ?: 'Unknown buyer'))));
        $buyers = $groups->map(function ($rows, $key) use ($shipments) {
            $tracking = $rows->pluck('tracking_number')->filter()->unique()->values();
            $linked = $shipments->filter(fn ($shipment) =>
                mb_strtolower(trim((string) $shipment->buyer_username)) === $key
                || ($shipment->tracking_number && $tracking->contains($shipment->tracking_number)));
            return [
                'username' => $rows->first()->buyer_username ?: 'Unknown buyer',
                'orders' => $rows,
                'shipments' => $linked->values(),
                'items' => (int) $rows->sum(fn ($order) => max(1, (int) $order->quantity)),
                'status' => $rows->pluck('shipping_status')->filter()->first() ?? 'pending',
            ];
        });
        // Shipment-only buyers remain visible when order synchronization is incomplete.
        foreach ($shipments as $shipment) {
            $key = mb_strtolower(trim((string) ($shipment->buyer_username ?: 'Unknown buyer')));
            if (! $buyers->has($key)) {
                $buyers->put($key, [
                    'username' => $shipment->buyer_username ?: 'Unknown buyer',
                    'orders' => collect(),
                    'shipments' => $shipments->filter(fn ($row) => mb_strtolower(trim((string) ($row->buyer_username ?: 'Unknown buyer'))) === $key)->values(),
                    'items' => (int) $shipments->filter(fn ($row) => mb_strtolower(trim((string) ($row->buyer_username ?: 'Unknown buyer'))) === $key)->sum('item_count'),
                    'status' => $shipment->status ?? 'pending',
                ]);
            }
        }
        $buyers = $buyers->sortKeys()->filter(function ($buyer) {
            $matchesSearch = $this->search === '' || str_contains(mb_strtolower($buyer['username']), mb_strtolower(trim($this->search)))
                || $buyer['orders']->contains(fn ($order) => str_contains(mb_strtolower((string) $order->whatnot_order_id), mb_strtolower(trim($this->search))))
                || $buyer['shipments']->contains(fn ($shipment) => str_contains(mb_strtolower((string) $shipment->tracking_number), mb_strtolower(trim($this->search))));
            return $matchesSearch && ($this->statusFilter === 'all' || $buyer['status'] === $this->statusFilter);
        })->values();
        return compact('show', 'buyers');
    }

    public static function safeDocumentUrl(mixed $url): ?string
    {
        if (! is_string($url) || strlen($url) > 4096 || preg_match('/[\\x00-\\x1f\\x7f]/', $url)) return null;
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) return null;
        return $url;
    }
}
