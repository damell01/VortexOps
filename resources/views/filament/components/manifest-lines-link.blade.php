@php
    $pallet = $this->record ?? null;
    $count  = $pallet?->lines()->count() ?? 0;
@endphp

<div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 px-5 py-4">
    <div>
        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
            {{ $count }} {{ Str::plural('line', $count) }} on this pallet
        </p>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Lines are typed as a table — one row each, with running totals — rather than as a form per line.
        </p>
    </div>

    @if($pallet)
        <a href="{{ \App\Filament\Resources\PalletResource::getUrl('add-lines', ['record' => $pallet]) }}"
            class="flex-shrink-0 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-violet-700 transition">
            {{ $count > 0 ? 'Edit manifest lines' : 'Add manifest lines' }}
        </a>
    @else
        <span class="flex-shrink-0 text-sm text-gray-500 dark:text-gray-400">
            Save the pallet first, then add its lines.
        </span>
    @endif
</div>
