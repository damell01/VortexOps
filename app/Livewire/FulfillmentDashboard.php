<?php

namespace App\Livewire;

use App\Models\FulfillmentPackage;
use App\Models\FulfillmentPackageItem;
use App\Models\Show;
use App\Models\StreamerLogItem;
use Livewire\Component;

class FulfillmentDashboard extends Component
{
    public Show $show;
    public string $filterStatus = 'all';
    public string $search = '';
    public string $scanCode = '';
    public array $notes = [];
    public ?int $activePackageId = null;
    public ?string $newPackageBuyer = null;

    public function mount(Show $show): void
    {
        $this->show = $show;
        $this->authorizeShow();
        $this->activePackageId = FulfillmentPackage::query()
            ->where('show_id', $this->show->id)
            ->where('status', 'building')
            ->latest('id')
            ->value('id');
    }

    public function createPackage(): void
    {
        $this->authorizeShow();

        $next = (int) FulfillmentPackage::where('show_id', $this->show->id)->max('box_number') + 1;
        $package = FulfillmentPackage::create([
            'show_id' => $this->show->id,
            'buyer_username' => filled($this->newPackageBuyer) ? ltrim(trim($this->newPackageBuyer), '@') : null,
            'box_number' => max(1, $next),
            'box_total' => max(1, $next),
            'tracking_number' => 'INTERNAL-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)),
            'created_by' => auth()->id(),
            'packed_by' => auth()->id(),
            'status' => 'building',
        ]);

        FulfillmentPackage::where('show_id', $this->show->id)
            ->whereKeyNot($package->id)
            ->update(['box_total' => max(1, $next)]);

        $this->activePackageId = $package->id;
        $this->newPackageBuyer = null;
        $this->dispatch('notify', message: "Box {$package->box_number} created");
    }

    public function selectPackage(int $packageId): void
    {
        $this->authorizeShow();
        $package = FulfillmentPackage::where('show_id', $this->show->id)->findOrFail($packageId);
        abort_if($package->isSealed(), 422, 'This box is already sealed.');
        $this->activePackageId = $package->id;
    }

    public function packOne(StreamerLogItem $line): void
    {
        $this->authorizeLine($line);
        if ($line->remainingToPack() <= 0) return;

        $package = $this->activePackage();
        abort_unless($package, 422, 'Create or select a box before packing items.');

        $packageItem = FulfillmentPackageItem::firstOrNew([
            'fulfillment_package_id' => $package->id,
            'streamer_log_item_id' => $line->id,
        ]);
        $packageItem->product_id = $line->inventory_item_id;
        $packageItem->quantity = (int) ($packageItem->quantity ?: 0) + 1;
        $packageItem->packed_by = auth()->id();
        $packageItem->packed_at = now();
        $packageItem->save();

        $line->packed_quantity = min((int) $line->quantity, (int) $line->packed_quantity + 1);
        $line->fulfillment_status = $line->packed_quantity >= $line->quantity
            ? StreamerLogItem::FULFILLMENT_FULFILLED
            : StreamerLogItem::FULFILLMENT_PENDING;
        $line->fulfilled_by = auth()->id();
        $line->fulfilled_at = $line->packed_quantity >= $line->quantity ? now() : null;
        $line->save();

        $this->dispatch('notify', message: $line->packingProgressLabel());
    }

    public function packRemaining(StreamerLogItem $line): void
    {
        $this->authorizeLine($line);
        $remaining = $line->remainingToPack();
        if ($remaining <= 0) return;

        $package = $this->activePackage();
        abort_unless($package, 422, 'Create or select a box before packing items.');

        $packageItem = FulfillmentPackageItem::firstOrNew([
            'fulfillment_package_id' => $package->id,
            'streamer_log_item_id' => $line->id,
        ]);
        $packageItem->product_id = $line->inventory_item_id;
        $packageItem->quantity = (int) ($packageItem->quantity ?: 0) + $remaining;
        $packageItem->packed_by = auth()->id();
        $packageItem->packed_at = now();
        $packageItem->save();

        $line->update([
            'packed_quantity' => (int) $line->quantity,
            'fulfillment_status' => StreamerLogItem::FULFILLMENT_FULFILLED,
            'fulfillment_note' => filled($this->notes[$line->id] ?? null) ? trim($this->notes[$line->id]) : $line->fulfillment_note,
            'fulfilled_by' => auth()->id(),
            'fulfilled_at' => now(),
        ]);

        $this->dispatch('notify', message: 'Item line fully packaged');
    }

    public function scanItem(): void
    {
        $this->authorizeShow();
        $code = trim($this->scanCode);
        if ($code === '') return;

        $report = $this->show->streamerLogEntry()->with('items.inventoryItem')->first();
        $line = $report?->items->first(function (StreamerLogItem $line) use ($code): bool {
            if ($line->remainingToPack() <= 0) return false;
            $item = $line->inventoryItem;
            return in_array($code, array_filter([$item?->barcode, $item?->upc, $item?->sku]), true);
        });

        if (! $line) {
            $this->dispatch('notify', message: "No remaining logged item matches {$code}");
            return;
        }

        $this->packOne($line);
        $this->scanCode = '';
    }

    public function markNotFulfilled(StreamerLogItem $line): void
    {
        $this->authorizeLine($line);
        $note = trim((string) ($this->notes[$line->id] ?? ''));

        $line->update([
            'fulfillment_status' => StreamerLogItem::FULFILLMENT_NOT_FULFILLED,
            'fulfillment_note' => $note !== '' ? $note : 'Packing issue',
            'fulfilled_by' => auth()->id(),
            'fulfilled_at' => now(),
        ]);

        $this->dispatch('notify', message: 'Item flagged for review');
    }

    public function resetFulfillment(StreamerLogItem $line): void
    {
        $this->authorizeLine($line);
        $line->packageItems()->delete();
        $line->update([
            'packed_quantity' => 0,
            'fulfillment_status' => null,
            'fulfillment_note' => null,
            'fulfilled_by' => null,
            'fulfilled_at' => null,
        ]);
        unset($this->notes[$line->id]);
        $this->dispatch('notify', message: 'Packing status reset');
    }

    public function sealPackage(int $packageId): void
    {
        $this->authorizeShow();
        $package = FulfillmentPackage::where('show_id', $this->show->id)->with('items')->findOrFail($packageId);
        abort_if($package->items->isEmpty(), 422, 'Add at least one item before sealing this box.');
        $package->update([
            'status' => 'sealed',
            'sealed_at' => now(),
            'packed_by' => auth()->id(),
        ]);
        if ($this->activePackageId === $package->id) $this->activePackageId = null;
        $this->dispatch('notify', message: "{$package->package_code} sealed and ready");
    }

    public function markLabelPrinted(int $packageId): void
    {
        $this->authorizeShow();
        $package = FulfillmentPackage::where('show_id', $this->show->id)->findOrFail($packageId);
        $package->update(['label_printed_at' => now()]);
    }

    protected function activePackage(): ?FulfillmentPackage
    {
        if (! $this->activePackageId) return null;
        return FulfillmentPackage::where('show_id', $this->show->id)
            ->where('status', 'building')
            ->find($this->activePackageId);
    }

    protected function authorizeShow(): void
    {
        $user = auth()->user();
        $allowed = $user && (
            $user->isAdmin()
            || $user->isOwner()
            || $user->isFulfillmentAdmin()
            || ($user->isFulfillment() && $this->show->fulfillmentUsers()->where('users.id', $user->id)->exists())
        );
        abort_unless($allowed, 403);
    }

    protected function authorizeLine(StreamerLogItem $line): void
    {
        $this->authorizeShow();
        $belongsToShow = $line->logEntry()->where('show_id', $this->show->id)->exists();
        abort_unless($belongsToShow, 403);
    }

    public function render()
    {
        $this->authorizeShow();

        $report = $this->show->streamerLogEntry()
            ->with(['items.inventoryItem', 'items.location', 'items.fulfilledBy', 'items.packageItems.package'])
            ->first();

        $allLines = $report?->items ?? collect();
        $lines = $allLines;

        if ($this->filterStatus !== 'all') {
            $lines = $lines->filter(fn (StreamerLogItem $line) => match ($this->filterStatus) {
                'pending' => $line->remainingToPack() > 0 && $line->fulfillmentStatus() !== StreamerLogItem::FULFILLMENT_NOT_FULFILLED,
                'fulfilled' => $line->remainingToPack() === 0,
                'not_fulfilled' => $line->fulfillmentStatus() === StreamerLogItem::FULFILLMENT_NOT_FULFILLED,
                default => true,
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

        foreach ($allLines as $line) {
            if (! array_key_exists($line->id, $this->notes) && filled($line->fulfillment_note)) {
                $this->notes[$line->id] = $line->fulfillment_note;
            }
        }

        $pendingCount = $allLines->sum(fn (StreamerLogItem $line) => $line->remainingToPack());
        $fulfilledCount = $allLines->filter(fn (StreamerLogItem $line) => $line->remainingToPack() === 0)->count();
        $notFulfilledCount = $allLines->filter(fn (StreamerLogItem $line) => $line->fulfillmentStatus() === StreamerLogItem::FULFILLMENT_NOT_FULFILLED)->count();

        $shipments = $this->show->shipments()->orderByDesc('created_at_whatnot')->orderByDesc('id')->limit(100)->get();
        $packages = FulfillmentPackage::query()
            ->where('show_id', $this->show->id)
            ->with(['items.streamerLogItem.inventoryItem', 'packedBy'])
            ->orderBy('box_number')
            ->get();
        $deliveredShipments = $shipments->filter(fn ($shipment) => strtolower((string) $shipment->status) === 'delivered')->count();

        return view('livewire.fulfillment-dashboard', [
            'show' => $this->show,
            'report' => $report,
            'lines' => $lines,
            'allLines' => $allLines,
            'pendingCount' => $pendingCount,
            'fulfilledCount' => $fulfilledCount,
            'notFulfilledCount' => $notFulfilledCount,
            'packages' => $packages,
            'activePackage' => $packages->firstWhere('id', $this->activePackageId),
            'shipments' => $shipments,
            'shipmentStats' => [
                'total' => $shipments->count(),
                'delivered' => $deliveredShipments,
                'open' => max(0, $shipments->count() - $deliveredShipments),
                'shipping_cost' => (float) $shipments->sum('shipping_cost'),
            ],
            'assignedUsers' => $this->show->fulfillmentUsers()->get(['users.id', 'users.name']),
        ]);
    }
}
