<x-filament-panels::page>
    @php($movement = $this->record)

    <div class="space-y-6">
        <div class="grid gap-6 lg:grid-cols-3">
            <x-filament::section class="lg:col-span-2">
                <x-slot name="heading">Movement Details</x-slot>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <div class="text-sm text-gray-500">Date &amp; Time</div>
                        <div class="font-medium">{{ $movement->created_at?->format('M j, Y g:i A') ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Movement Type</div>
                        <div class="font-medium">{{ \App\Models\InventoryMovement::movementTypeLabels()[$movement->movement_type] ?? $movement->movement_type }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Item</div>
                        <div class="font-medium">{{ $movement->item?->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">SKU</div>
                        <div class="font-medium">{{ $movement->item?->sku ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Quantity</div>
                        <div class="font-medium">{{ number_format((float) $movement->quantity, 0) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Created By</div>
                        <div class="font-medium">{{ $movement->createdByUser?->name ?? 'System' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">From Location</div>
                        <div class="font-medium">{{ $movement->fromLocation?->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">To Location</div>
                        <div class="font-medium">{{ $movement->toLocation?->name ?? '—' }}</div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="text-sm text-gray-500">Reason</div>
                    <div class="mt-1 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-900">
                        {{ $movement->reason ?: 'No reason was added for this movement.' }}
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Reference</x-slot>

                <div class="space-y-3 text-sm">
                    <div>
                        <div class="text-gray-500">Source Record</div>
                        <div class="font-medium">
                            @if ($movement->reference_type && $movement->reference_id)
                                {{ $movement->reference_type }} #{{ $movement->reference_id }}
                            @else
                                Manual update
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-gray-500">Inventory Value Impact</div>
                        <div class="font-medium">
                            ${{ number_format((float) $movement->quantity * (float) ($movement->item?->unit_cost ?? 0), 2) }}
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
