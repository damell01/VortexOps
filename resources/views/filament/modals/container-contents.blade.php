@php
    /** @var \App\Models\InventoryItem $record */
    $contents = $record->childContents;
    $totalUnits = $contents->sum('quantity_per_parent');
@endphp

<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800/60">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $record->name }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ filled($record->barcode) ? 'Case barcode: ' . $record->barcode : 'No case barcode saved' }}
                    @if(filled($record->sku)) · SKU: {{ $record->sku }} @endif
                </p>
            </div>
            <div class="flex gap-2 text-center">
                <div class="min-w-16 rounded-lg bg-white px-3 py-2 dark:bg-gray-900">
                    <p class="text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $contents->count() }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-gray-500">SKUs</p>
                </div>
                <div class="min-w-16 rounded-lg bg-white px-3 py-2 dark:bg-gray-900">
                    <p class="text-lg font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($totalUnits) }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-gray-500">Units/case</p>
                </div>
            </div>
        </div>
    </div>

    @if ($contents->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 px-5 py-8 text-center dark:border-gray-700">
            <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-500 dark:bg-gray-800">
                <x-heroicon-o-archive-box class="h-5 w-5" />
            </div>
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Contents have not been mapped yet</p>
            <p class="mx-auto mt-1 max-w-md text-xs leading-5 text-gray-500 dark:text-gray-400">
                This product is correctly marked as a case/container, but the individual SKUs inside it are not defined yet. You can still keep and receive the sealed case as inventory.
            </p>
            <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('edit', ['record' => $record]) }}"
               class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white hover:bg-primary-500">
                Map Case Contents
            </a>
        </div>
    @else
        <div>
            <div class="mb-2 flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">What one case contains</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">These quantities are used when the case is broken into individual inventory.</p>
                </div>
                <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('edit', ['record' => $record]) }}" class="shrink-0 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Edit contents</a>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                @foreach ($contents as $line)
                    @php
                        $child = $line->childItem;
                        $onHand = (float) ($child?->stock->sum('quantity') ?? 0);
                    @endphp
                    <div class="border-b border-gray-100 p-4 last:border-0 dark:border-gray-800" wire:key="content-{{ $line->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $child?->name ?? 'Item removed from catalogue' }}</p>
                                    <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs font-bold text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                                        {{ number_format($line->quantity_per_parent) }} per case
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    SKU: {{ $child?->sku ?: '—' }}
                                    @if (filled($child?->barcode)) · Barcode: {{ $child->barcode }} @endif
                                    @if(filled($line->unit_type)) · {{ ucfirst($line->unit_type) }} @endif
                                </p>
                            </div>
                            @if ($child)
                                <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $child]) }}"
                                   class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                                    Open
                                </a>
                            @endif
                        </div>
                        @if($child)
                            <div class="mt-3 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-gray-800/60">
                                <span class="text-gray-500 dark:text-gray-400">Individual stock currently on hand</span>
                                <span class="font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($onHand) }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-lg bg-amber-50 px-3 py-2.5 text-xs leading-5 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
            <strong>Breaking a case changes inventory.</strong> The case quantity is reduced and the mapped individual SKUs are added at the same location. Use the Break Case action only when the physical case is actually opened.
        </div>
    @endif
</div>
