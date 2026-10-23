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
</div>
