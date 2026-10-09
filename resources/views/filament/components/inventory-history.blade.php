<div class="space-y-4">
    <div>
        <h3 class="text-lg font-semibold">{{ $item->name }}</h3>
        <p class="text-sm text-gray-500">{{ $item->sku }} · Latest 100 recorded stock changes</p>
    </div>
    @forelse($movements as $movement)
        <article class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <strong>{{ \App\Models\InventoryMovement::movementTypeLabels()[$movement->movement_type] ?? $movement->movement_type }}</strong>
                <span class="font-semibold">{{ $movement->changeLabel(2) }} units</span>
            </div>
            <p class="mt-1 text-sm">{{ $movement->reason ?: 'No reason recorded' }}</p>
            <p class="mt-1 text-xs text-gray-500">
                @if($movement->fromLocation) From {{ $movement->fromLocation->name }} @endif
                @if($movement->toLocation) To {{ $movement->toLocation->name }} @endif
                · {{ $movement->created_at?->format('M j, Y g:i A') }}
                · {{ $movement->createdByUser?->name ?? 'System' }}
            </p>
        </article>
    @empty
        <p class="rounded-xl bg-gray-50 p-4 text-sm dark:bg-gray-800">No stock changes are recorded for this item yet.</p>
    @endforelse
</div>
