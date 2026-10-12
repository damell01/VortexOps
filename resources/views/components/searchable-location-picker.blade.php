@props(['model', 'label' => 'Location'])
@php($locations = \App\Models\InventoryLocation::activeOptions())
<div class="relative mb-3" x-data="{ open:false, query:'', selected:$wire.entangle(@js($model)).live, names:@js($locations) }" @click.outside="open=false" @keydown.escape.stop="open=false">
    <span class="mb-1.5 block text-xs font-semibold" id="{{ $model }}-label">{{ $label }}</span>
    <button type="button" role="combobox" aria-labelledby="{{ $model }}-label" :aria-expanded="open" aria-controls="{{ $model }}-options" @click="open=!open; if(open) $nextTick(()=>$refs.search.focus())" class="flex min-h-11 w-full items-center justify-between rounded-xl border border-gray-300 bg-white px-3 text-left text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        <span x-text="names[selected] || 'Choose location'"></span><x-heroicon-o-chevron-down class="h-4 w-4" />
    </button>
    <div x-show="open" x-cloak class="absolute z-30 mt-1 w-full rounded-xl border border-gray-200 bg-white p-2 shadow-xl dark:border-gray-600 dark:bg-gray-800">
        <input x-ref="search" x-model="query" type="search" aria-label="Search locations" placeholder="Search locations…" class="mb-2 min-h-11 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900" />
        <div role="listbox" id="{{ $model }}-options" class="max-h-60 overflow-y-auto">
            @foreach($locations as $id => $name)
                <button type="button" role="option" :aria-selected="Number(selected) === {{ (int) $id }}" x-show="@js(mb_strtolower($name)).includes(query.toLowerCase())" @click="selected={{ (int) $id }}; open=false; query=''" class="block min-h-11 w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-primary-50 focus:bg-primary-50 dark:hover:bg-gray-700">{{ $name }}</button>
            @endforeach
            <p x-show="!Object.values(names).some(name=>name.toLowerCase().includes(query.toLowerCase()))" class="px-3 py-2 text-sm text-gray-500">No matching locations.</p>
        </div>
    </div>
</div>
