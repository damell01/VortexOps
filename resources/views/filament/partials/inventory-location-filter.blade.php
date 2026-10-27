<div class="mb-3 flex min-w-0 flex-wrap items-end gap-3">
    <label class="grid min-w-0 flex-1 gap-1 text-sm font-semibold" style="max-width:28rem">
        Inventory location
        <select wire:model.live="inventoryLocation" aria-label="Inventory location"
            class="min-h-11 w-full min-w-0 rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-800">
            <option value="">All locations</option>
            @foreach ($this->inventoryLocationOptions() as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </label>
    @if ($inventoryLocation !== '')
        <button type="button" wire:click="$set('inventoryLocation', '')" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-primary-600">All locations</button>
    @endif
    @if (method_exists($this, 'locationCoverage') && ($coverage = $this->locationCoverage()))
        @php($locationName = $this->inventoryLocationOptions()[(int) $inventoryLocation] ?? 'this location')
        <p class="w-full text-xs leading-5 text-gray-600 dark:text-gray-400" data-vx-location-coverage>
            <strong class="text-gray-900 dark:text-gray-100">{{ $locationName }}: {{ number_format($coverage['here']) }} {{ \Illuminate\Support\Str::plural('product', $coverage['here']) }}, {{ rtrim(rtrim(number_format($coverage['units'], 2), '0'), '.') }} {{ \Illuminate\Support\Str::plural('unit', (int) $coverage['units']) }} on hand.</strong>
            @if ($coverage['here'] < $coverage['total'])
                The other {{ number_format($coverage['total'] - $coverage['here']) }} of your {{ number_format($coverage['total']) }} products:
                @foreach ($coverage['elsewhere'] as $name => $count){{ number_format($count) }} stocked at {{ $name }}{{ $loop->last ? '' : ', ' }}@endforeach{{ $coverage['elsewhere'] && $coverage['unstocked'] ? '; ' : '' }}@if ($coverage['unstocked']){{ number_format($coverage['unstocked']) }} with no stock anywhere (imported or created, never received)@endif.
                <button type="button" wire:click="$set('inventoryLocation', '')" class="font-semibold text-primary-600">See all products</button>
            @endif
        </p>
    @endif
</div>
