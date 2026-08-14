@php
    /** @var \App\Models\InventoryItem $record */
    // Eager-loaded by the action; lazy loading is disabled outside production,
    // so touching these relations unloaded would fatal on first open.
    $contents = $record->childContents;
@endphp

<div class="vx-contents">
    @if ($contents->isEmpty())
        <p class="vx-contents-empty">
            This item is marked as a container but nothing has been listed inside it yet.
            Edit the item to add its contents.
        </p>
    @else
        <p class="vx-contents-intro">
            {{ $contents->count() }}
            {{ \Illuminate\Support\Str::plural('item', $contents->count()) }}
            inside <strong>{{ $record->name }}</strong>.
        </p>

        <ul class="vx-contents-list">
            @foreach ($contents as $line)
                @php
                    $child = $line->childItem;
                    $onHand = $child?->stock->sum('quantity');
                @endphp
                <li class="vx-contents-row" wire:key="content-{{ $line->id }}">
                    <span class="vx-contents-main">
                        <span class="vx-contents-name">
                            {{ $child?->name ?? 'Item removed from catalogue' }}
                        </span>
                        <span class="vx-contents-sku">
                            SKU: {{ $child?->sku ?: '—' }}
                            @if (filled($child?->barcode))
                                · Barcode: {{ $child->barcode }}
                            @endif
                        </span>
                    </span>

                    <span class="vx-contents-qty">
                        <span class="vx-contents-qty-value">{{ number_format($line->quantity_per_parent) }}</span>
                        <span class="vx-contents-qty-label">
                            per {{ $line->unit_type ?: 'container' }}
                        </span>
                    </span>

                    <span class="vx-contents-stock">
                        @if ($child)
                            <span class="vx-contents-stock-value">{{ number_format((float) $onHand) }}</span>
                            <span class="vx-contents-stock-label">on hand</span>
                        @else
                            <span class="vx-contents-stock-label">—</span>
                        @endif
                    </span>

                    @if ($child)
                        <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $child]) }}"
                           class="vx-contents-link">
                            Open
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
