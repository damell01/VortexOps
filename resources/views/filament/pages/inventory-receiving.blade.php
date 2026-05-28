<x-filament-panels::page>
    <div class="space-y-6">
        @unless (\App\Models\InventoryContainer::schemaReady())
            <section class="rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 text-amber-600" />
                    <div>
                        <h2 class="text-sm font-semibold text-amber-900">Inventory workflow needs the latest migration</h2>
                        <p class="mt-1 text-sm text-amber-800">
                            The container-tracking tables are not available yet on this environment. Run the latest migrations, then this screen will handle pallet receipts, case breakdown, and putaway normally.
                        </p>
                    </div>
                </div>
            </section>
        @endunless

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-600">Inbound Workflow</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950">Receive inventory into pallets, cases, or boxes</h1>
                    <p class="mt-2 max-w-3xl text-sm text-slate-600">
                        Start the warehouse flow here. This creates the inbound container, updates on-hand stock, and stores cost details for later landed-cost tracking.
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Flow Step</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">1. Receive</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Next</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">2. Break down pallet</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs uppercase tracking-[0.16em] text-slate-500">Then</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">3. Put cases away</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.1fr,0.9fr]">
            <form wire:submit="receiveInventory" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950">New inbound receipt</h2>
                        <p class="mt-1 text-sm text-slate-500">Capture the inbound container and attach the cost details you already know.</p>
                    </div>
                    <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-cyan-700">Receiving</span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Inventory item</span>
                        <select wire:model="inventory_item_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm">
                            <option value="">Select item</option>
                            @foreach ($this->itemOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('inventory_item_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Receiving location</span>
                        <select wire:model="inventory_location_id" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm">
                            <option value="">Select location</option>
                            @foreach ($this->receivingLocationOptions() as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('inventory_location_id') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Container type</span>
                        <select wire:model="container_type" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm">
                            <option value="pallet">Pallet</option>
                            <option value="case">Case</option>
                            <option value="box">Box</option>
                            <option value="unit">Loose unit</option>
                            <option value="other">Other</option>
                        </select>
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Units received</span>
                        <input wire:model="quantity" type="number" step="0.01" min="0.01" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                        @error('quantity') <p class="text-sm text-rose-600">{{ $message }}</p> @enderror
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Container label</span>
                        <input wire:model="label" type="text" placeholder="Auto-generated if blank" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Barcode / scanner ID</span>
                        <input wire:model="barcode" type="text" placeholder="Optional for future scanner flows" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-4">
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Seller unit cost</span>
                        <input wire:model="seller_unit_cost" type="number" step="0.01" min="0" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                    </label>
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Shipping unit cost</span>
                        <input wire:model="shipping_unit_cost" type="number" step="0.01" min="0" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                    </label>
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Other unit fees</span>
                        <input wire:model="other_unit_fees" type="number" step="0.01" min="0" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                    </label>
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Average unit cost</span>
                        <input wire:model="average_unit_cost" type="number" step="0.01" min="0" placeholder="Optional override" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm" />
                    </label>
                </div>

                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <input wire:model="scanner_ready" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" />
                    Mark this inbound container as scanner-ready
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Receipt notes</span>
                        <textarea wire:model="receipt_notes" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm"></textarea>
                    </label>
                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-700">Cost notes</span>
                        <textarea wire:model="cost_notes" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm"></textarea>
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-slate-500">
                        @if ($this->selectedItem())
                            Receiving into <span class="font-semibold text-slate-900">{{ $this->selectedItem()->name }}</span>
                        @else
                            Pick an item and location to create the inbound container.
                        @endif
                    </div>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-2xl bg-cyan-500 px-5 py-3 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-cyan-400">
                        <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                        Receive inventory
                    </button>
                </div>
            </form>

            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-950">Why this screen matters</h2>
                    <div class="mt-4 space-y-4 text-sm text-slate-600">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="font-semibold text-slate-900">Stock + container stay aligned</p>
                            <p class="mt-1">A receipt here creates the physical container record and the receiving-location stock in one step.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="font-semibold text-slate-900">Costing starts here</p>
                            <p class="mt-1">Seller cost, shipping, and fees get attached at intake so landed cost reporting can grow later without rework.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="font-semibold text-slate-900">Scanner-ready foundation</p>
                            <p class="mt-1">Barcodes are optional today, but this keeps the data structure ready for future scanning flows.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-950">Recent inbound containers</h2>
                        <span class="text-xs uppercase tracking-[0.16em] text-slate-400">Latest 6</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        @forelse ($this->recentReceipts() as $receipt)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $receipt->label }}</p>
                                        <p class="mt-1 text-sm text-slate-500">
                                            {{ $receipt->item?->name ?? 'Unknown item' }}
                                            @if ($receipt->item?->sku)
                                                <span class="text-slate-400">· {{ $receipt->item->sku }}</span>
                                            @endif
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700">{{ number_format((float) $receipt->quantity, 0) }} units</span>
                                </div>
                                <p class="mt-2 text-xs uppercase tracking-[0.16em] text-slate-400">{{ $receipt->location?->name ?: 'No location' }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No inbound containers yet.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-filament-panels::page>
