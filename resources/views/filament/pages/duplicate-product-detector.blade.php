<x-filament-panels::page>
<div class="space-y-6">

{{-- ── Flash Messages ──────────────────────────────────────────────────────── --}}
@if($flashOk)
    <div class="rounded-lg bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800 px-5 py-3 flex items-center gap-3">
        <x-heroicon-o-check-circle class="h-5 w-5 text-green-500 flex-shrink-0" />
        <span class="text-sm text-green-800 dark:text-green-200">{{ $flashOk }}</span>
        <button wire:click="$set('flashOk', null)" class="ml-auto text-green-400 hover:text-green-600">✕</button>
    </div>
@endif
@if($flashError)
    <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-5 py-3 flex items-center gap-3">
        <x-heroicon-o-exclamation-circle class="h-5 w-5 text-red-400 flex-shrink-0" />
        <span class="text-sm text-red-700 dark:text-red-300">{{ $flashError }}</span>
        <button wire:click="$set('flashError', null)" class="ml-auto text-red-400 hover:text-red-600">✕</button>
    </div>
@endif

{{-- ── Info + Scan Button ────────────────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Duplicate Product Detector</h2>
            <p class="text-sm text-gray-400 mt-1">
                Compares all active products using name similarity, brand, year, set, configuration, UPC, and sport.
                Products scoring ≥ 80% similar are shown as candidates for review.
            </p>
        </div>
        <button wire:click="scan" wire:loading.attr="disabled" type="button"
            class="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
            <span wire:loading wire:target="scan" class="animate-spin">⟳</span>
            <span wire:loading.remove wire:target="scan">🔍</span>
            Scan for Duplicates
        </button>
    </div>
</div>

{{-- ── Results ───────────────────────────────────────────────────────────────── --}}
@if($scanning)
<div class="text-center py-12 text-gray-400 text-sm">Scanning products…</div>
@elseif(count($pairs) === 0 && ! $scanning)
<div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 px-8 py-14 text-center space-y-2">
    <x-heroicon-o-document-duplicate class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No duplicate candidates found</p>
    <p class="text-xs text-gray-400 dark:text-gray-500">Click "Scan for Duplicates" to analyze your product catalog</p>
</div>
@endif

@foreach($pairs as $pair)
<div class="rounded-xl border border-orange-200 dark:border-orange-800 bg-white dark:bg-gray-900 overflow-hidden">
    {{-- Header --}}
    <div class="px-5 py-3 flex items-center justify-between bg-orange-50 dark:bg-orange-950">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-bold
                {{ $pair['score'] >= 95 ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' :
                   ($pair['score'] >= 88 ? 'bg-orange-100 dark:bg-orange-900 text-orange-700 dark:text-orange-300' :
                   'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300') }}">
                {{ $pair['score'] }}% Similar
            </span>
            <span class="text-xs text-orange-600 dark:text-orange-400">Possible duplicate</span>
        </div>
        <button wire:click="ignore('{{ $pair['key'] }}')" type="button"
            class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
            Mark as Different
        </button>
    </div>

    {{-- Two products side by side --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100 dark:divide-gray-800">
        @foreach(['product_a', 'product_b'] as $side)
        @php $prod = $pair[$side]; @endphp
        <div class="px-5 py-4 space-y-1.5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $prod['name'] }}</p>
                    <p class="text-xs text-gray-400">
                        {{ implode(' · ', array_filter([$prod['brand'], $prod['year'] ? (string)$prod['year'] : null, $prod['type']])) }}
                    </p>
                </div>
                <a href="/admin/inventory-items/{{ $prod['id'] }}"
                    class="flex-shrink-0 text-xs text-blue-500 hover:underline"
                    target="_blank">View ↗</a>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Reasons --}}
    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
        <div class="flex flex-wrap gap-2">
            @foreach($pair['reasons'] as $reason)
                <span class="inline-flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-2 py-0.5 rounded-full">
                    ✓ {{ $reason }}
                </span>
            @endforeach
        </div>
    </div>

    {{-- Merge actions --}}
    <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-800 flex flex-wrap gap-2">
        <p class="text-xs text-gray-400 self-center mr-2">Merge into:</p>
        <button wire:click="merge({{ $pair['product_a']['id'] }}, {{ $pair['product_b']['id'] }})"
            wire:confirm="Keep '{{ addslashes($pair['product_a']['name']) }}' and delete '{{ addslashes($pair['product_b']['name']) }}'? This cannot be undone."
            type="button"
            class="rounded-lg bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700">
            Keep {{ Str::limit($pair['product_a']['name'], 30) }}
        </button>
        <button wire:click="merge({{ $pair['product_b']['id'] }}, {{ $pair['product_a']['id'] }})"
            wire:confirm="Keep '{{ addslashes($pair['product_b']['name']) }}' and delete '{{ addslashes($pair['product_a']['name']) }}'? This cannot be undone."
            type="button"
            class="rounded-lg bg-green-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-green-700">
            Keep {{ Str::limit($pair['product_b']['name'], 30) }}
        </button>
    </div>
</div>
@endforeach

@if(count($pairs) > 0)
<p class="text-center text-xs text-gray-400">
    {{ count($pairs) }} candidate pair(s) found · {{ count($ignored) }} ignored
</p>
@endif

</div>
</x-filament-panels::page>
