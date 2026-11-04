<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\WhatnotShowOrder;
use App\Services\FulfillmentShipmentRefreshService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Filament\Resources\Pages\Page;

class ShippingDesk extends Page
{
    protected static string $resource = FulfillmentResource::class;
    protected static bool $shouldRegisterNavigation = false;

    public int $record;
    public string $search = '';
    public string $statusFilter = 'all';
    public string $viewMode = 'buyers';
    public array $selectedShipmentIds = [];
    public array $printDocuments = [];

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

    public function isManager(): bool
    {
        $user = auth()->user();
        return (bool) ($user && ($user->isOwner() || $user->isAdmin() || $user->isFulfillmentAdmin()));
    }

    public function refreshShipments(): void
    {
        $show = FulfillmentResource::getEloquentQuery()->findOrFail($this->record);
        abort_unless(FulfillmentResource::canView($show), 403);
        $actor = auth()->user();
        abort_unless($actor && ($actor->isOwner() || $actor->isAdmin() || $actor->isFulfillmentAdmin()), 403);
        try {
            $result = app(FulfillmentShipmentRefreshService::class)->refresh($show);
            Notification::make()->title('Shipment refresh complete')
                ->body($result['matched'].' matching shipments checked; '.$result['updated'].' updated.')
                ->success()->send();
        } catch (ValidationException $exception) {
            Notification::make()->title('Shipment refresh unavailable')
                ->body(collect($exception->errors())->flatten()->first() ?: 'Check API connection and permissions.')
                ->warning()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->title('Shipment refresh failed')
                ->body('Review the Whatnot API connection and application logs.')
                ->danger()->send();
        }
    }

    public function preparePrintBatch(): void
    {
        $show = FulfillmentResource::getEloquentQuery()->findOrFail($this->record);
        abort_unless(FulfillmentResource::canView($show), 403);
        $ids = collect($this->selectedShipmentIds)->filter(fn ($id) => ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        if (! $ids || count($ids) > 100) {
            throw ValidationException::withMessages(['selectedShipmentIds' => 'Select between 1 and 100 shipments.']);
        }
        $shipments = Shipment::query()->where('show_id', $show->id)->whereIn('id', $ids)
            ->orderBy('buyer_username')->orderBy('id')->get();
        if ($shipments->count() !== count($ids)) abort(403);
        $documents = $shipments->map(function (Shipment $shipment) {
            $raw = is_array($shipment->raw_payload) ? $shipment->raw_payload : [];
            $docs = is_array($raw['documents'] ?? null) ? $raw['documents'] : [];
            $url = self::safeDocumentUrl($docs['label_url'] ?? null);
            return $url ? ['buyer' => $shipment->buyer_username ?: 'Unknown buyer', 'url' => $url] : null;
        })->filter()->values()->all();
        if (count($documents) !== count($ids)) {
            throw ValidationException::withMessages(['selectedShipmentIds' => 'Every selected shipment must have a synced, printable label URL.']);
        }
        \Illuminate\Support\Facades\DB::table('fulfillment_print_jobs')->insert([
            'show_id' => $show->id, 'created_by' => auth()->id(),
            'shipment_ids' => json_encode($ids), 'document_count' => count($documents),
            'status' => 'prepared', 'prepared_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->printDocuments = $documents;
        Notification::make()->title('Label batch prepared')->body(count($documents).' labels ready to open individually. Printing is not yet confirmed.')->success()->send();
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
        $allShipments = $shipments;
        $statusCounts = [
            'total' => $allShipments->count(),
            'pending' => $allShipments->filter(fn ($shipment) => in_array($shipment->status, ['created', 'pending', 'ready_to_ship', null], true))->count(),
            'labels' => $allShipments->filter(fn ($shipment) => in_array($shipment->status, ['label_generated', 'label_created'], true))->count(),
            'shipped' => $allShipments->filter(fn ($shipment) => in_array($shipment->status, ['dropped_off', 'in_transit', 'shipped'], true))->count(),
            'delivered' => $allShipments->where('status', 'delivered')->count(),
            'exceptions' => $allShipments->filter(fn ($shipment) => in_array($shipment->status, ['failed', 'returned', 'cancelled', 'cancelled_not_shipped', 'cancelled_not_shipped_with_dropoff', 'cancelled_tracking_stuck'], true))->count(),
        ];
        $lastSynced = $orders->pluck('shipment_synced_at')->filter()->max();
        return compact('show', 'buyers', 'statusCounts', 'lastSynced');
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
