<?php

namespace App\Livewire;

use App\Models\Show;
use App\Models\StreamerLogItem;
use Livewire\Component;

class FulfillmentDashboard extends Component
{
    public Show $show;
    public string $filterStatus = 'all';
    public string $search = '';
    public array $notes = [];

    public function mount(Show $show): void
    {
        $this->show = $show;
    }

    public function markFulfilled(StreamerLogItem $line): void
    {
        $this->authorizeLine($line);

        $line->update([
            'fulfillment_status' => StreamerLogItem::FULFILLMENT_FULFILLED,
            'fulfillment_note' => filled($this->notes[$line->id] ?? null) ? trim($this->notes[$line->id]) : null,
            'fulfilled_by' => auth()->id(),
            'fulfilled_at' => now(),
        ]);

        $this->dispatch('notify', message: 'Item marked fulfilled');
    }

    public function markNotFulfilled(StreamerLogItem $line): void
    {
        $this->authorizeLine($line);
        $note = trim((string) ($this->notes[$line->id] ?? ''));

        $line->update([
            'fulfillment_status' => StreamerLogItem::FULFILLMENT_NOT_FULFILLED,
            'fulfillment_note' => $note !== '' ? $note : 'Not fulfilled',
            'fulfilled_by' => auth()->id(),
            'fulfilled_at' => now(),
        ]);

        $this->dispatch('notify', message: 'Item marked not fulfilled');
    }

    public function resetFulfillment(StreamerLogItem $line): void
    {
        $this->authorizeLine($line);

        $line->update([
            'fulfillment_status' => null,
            'fulfillment_note' => null,
            'fulfilled_by' => null,
            'fulfilled_at' => null,
        ]);

        unset($this->notes[$line->id]);
        $this->dispatch('notify', message: 'Fulfillment status reset');
    }

    public function markAllFulfilled(): void
    {
        $report = $this->show->streamerLogEntry()->first();
        if (! $report) {
            return;
        }

        $report->items()
            ->where(function ($query) {
                $query->whereNull('fulfillment_status')
                    ->orWhere('fulfillment_status', StreamerLogItem::FULFILLMENT_PENDING);
            })
            ->update([
                'fulfillment_status' => StreamerLogItem::FULFILLMENT_FULFILLED,
                'fulfilled_by' => auth()->id(),
                'fulfilled_at' => now(),
                'updated_at' => now(),
            ]);

        $this->dispatch('notify', message: 'All pending logged items marked fulfilled');
    }

    protected function authorizeLine(StreamerLogItem $line): void
    {
        $belongsToShow = $line->logEntry()
            ->where('show_id', $this->show->id)
            ->exists();

        abort_unless($belongsToShow, 403);
    }

    public function render()
    {
        $report = $this->show->streamerLogEntry()
            ->with(['items.inventoryItem', 'items.location', 'items.fulfilledBy'])
            ->first();

        $allLines = $report?->items ?? collect();
        $lines = $allLines;

        if ($this->filterStatus !== 'all') {
            $lines = $lines->filter(function (StreamerLogItem $line) {
                return $line->fulfillmentStatus() === $this->filterStatus;
            });
        }

        if (filled($this->search)) {
            $needle = mb_strtolower(trim($this->search));
            $lines = $lines->filter(function (StreamerLogItem $line) use ($needle) {
                $item = $line->inventoryItem;
                $haystack = implode(' ', array_filter([
                    $line->item_name,
                    $item?->name,
                    $item?->sku,
                    $item?->barcode,
                    $item?->upc,
                    $line->dispositionLabel(),
                    $line->location?->name,
                ]));

                return str_contains(mb_strtolower($haystack), $needle);
            });
        }

        $pendingCount = $allLines->filter(fn (StreamerLogItem $line) => ! $line->isFulfillmentReviewed())->count();
        $fulfilledCount = $allLines->filter(fn (StreamerLogItem $line) => $line->fulfillmentStatus() === StreamerLogItem::FULFILLMENT_FULFILLED)->count();
        $notFulfilledCount = $allLines->filter(fn (StreamerLogItem $line) => $line->fulfillmentStatus() === StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();

        foreach ($allLines as $line) {
            if (! array_key_exists($line->id, $this->notes) && filled($line->fulfillment_note)) {
                $this->notes[$line->id] = $line->fulfillment_note;
            }
        }

        $shipments = $this->show->shipments()
            ->orderByDesc('created_at_whatnot')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $deliveredShipments = $shipments->filter(fn ($shipment) => strtolower((string) $shipment->status) === 'delivered')->count();
        $openShipments = max(0, $shipments->count() - $deliveredShipments);

        return view('livewire.fulfillment-dashboard', [
            'show' => $this->show,
            'report' => $report,
            'lines' => $lines,
            'allLines' => $allLines,
            'pendingCount' => $pendingCount,
            'fulfilledCount' => $fulfilledCount,
            'notFulfilledCount' => $notFulfilledCount,
            'shipments' => $shipments,
            'shipmentStats' => [
                'total' => $shipments->count(),
                'delivered' => $deliveredShipments,
                'open' => $openShipments,
                'shipping_cost' => (float) $shipments->sum('shipping_cost'),
            ],
            'assignedUsers' => $this->show->fulfillmentUsers()->get(['users.id', 'users.name']),
        ]);
    }
}
