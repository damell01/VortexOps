<?php

namespace App\Livewire;

use App\Models\DeductionRequestLine;
use App\Models\Show;
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

    public function markFulfilled(DeductionRequestLine $line): void
    {
        $this->authorizeLine($line);

        $line->update([
            'fulfillment_status' => DeductionRequestLine::FULFILLMENT_FULFILLED,
            'fulfillment_note' => filled($this->notes[$line->id] ?? null) ? trim($this->notes[$line->id]) : null,
            'fulfilled_by' => auth()->id(),
            'fulfilled_at' => now(),
        ]);

        $this->dispatch('notify', message: 'Item marked fulfilled');
    }

    public function markNotFulfilled(DeductionRequestLine $line): void
    {
        $this->authorizeLine($line);
        $note = trim((string) ($this->notes[$line->id] ?? ''));

        $line->update([
            'fulfillment_status' => DeductionRequestLine::FULFILLMENT_NOT_FULFILLED,
            'fulfillment_note' => $note !== '' ? $note : 'Not fulfilled',
            'fulfilled_by' => auth()->id(),
            'fulfilled_at' => now(),
        ]);

        $this->dispatch('notify', message: 'Item marked not fulfilled');
    }

    public function resetFulfillment(DeductionRequestLine $line): void
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
        $request = $this->show->latestDeductionRequest()->first();
        if (! $request) {
            return;
        }

        $request->lines()
            ->where(function ($query) {
                $query->whereNull('fulfillment_status')
                    ->orWhere('fulfillment_status', DeductionRequestLine::FULFILLMENT_PENDING);
            })
            ->update([
                'fulfillment_status' => DeductionRequestLine::FULFILLMENT_FULFILLED,
                'fulfilled_by' => auth()->id(),
                'fulfilled_at' => now(),
                'updated_at' => now(),
            ]);

        $this->dispatch('notify', message: 'All pending logged items marked fulfilled');
    }

    protected function authorizeLine(DeductionRequestLine $line): void
    {
        $belongsToShow = $line->request()
            ->where('show_id', $this->show->id)
            ->exists();

        abort_unless($belongsToShow, 403);
    }

    public function render()
    {
        $request = $this->show->latestDeductionRequest()
            ->with(['lines.inventoryItem', 'lines.location', 'lines.fulfilledBy'])
            ->first();

        $lines = $request?->lines ?? collect();

        if ($this->filterStatus !== 'all') {
            $lines = $lines->filter(function (DeductionRequestLine $line) {
                return $line->fulfillmentStatus() === $this->filterStatus;
            });
        }

        if (filled($this->search)) {
            $needle = mb_strtolower(trim($this->search));
            $lines = $lines->filter(function (DeductionRequestLine $line) use ($needle) {
                $item = $line->inventoryItem;
                $haystack = implode(' ', array_filter([
                    $item?->name,
                    $item?->sku,
                    $item?->barcode,
                    $item?->upc,
                    $line->raw_description,
                    $line->location?->name,
                ]));

                return str_contains(mb_strtolower($haystack), $needle);
            });
        }

        $allLines = $request?->lines ?? collect();
        $pendingCount = $allLines->filter(fn (DeductionRequestLine $line) => ! $line->isFulfillmentReviewed())->count();
        $fulfilledCount = $allLines->filter(fn (DeductionRequestLine $line) => $line->fulfillmentStatus() === DeductionRequestLine::FULFILLMENT_FULFILLED)->count();
        $notFulfilledCount = $allLines->filter(fn (DeductionRequestLine $line) => $line->fulfillmentStatus() === DeductionRequestLine::FULFILLMENT_NOT_FULFILLED)->count();

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
            'request' => $request,
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
