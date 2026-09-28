@php
    $rows = $this->getCandidates();
@endphp
<x-filament-panels::page>
<div class="space-y-5">
    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div>
            <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Confirmed no-shows</h2>
            <p class="mt-1 max-w-2xl text-xs text-gray-500">
                A show only lands here because Whatnot itself reported an explicit 0-minute duration at least
                two days after its scheduled date — the one signal in this app that means a show genuinely
                never aired, not just "hasn't been checked yet." Deleting is permanent.
            </p>
        </div>
        @if($rows->isNotEmpty())
            {{ $this->deleteAllClearAction }}
        @endif
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-800">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $rows->count() }} confirmed no-show(s)</div>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($rows as $row)
                @php $show = $row['show']; $blocking = $row['blocking']; @endphp
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <a href="{{ $this->showUrl($show->id) }}" class="truncate text-sm font-semibold text-gray-950 hover:text-primary-600 dark:text-white">{{ $show->title }}</a>
                        <div class="mt-1 text-[11px] text-gray-500">
                            {{ $show->show_date?->format('M j, Y') }} · {{ $show->channel?->name ?: 'No channel' }}
                            @if($show->analytics_unavailable_at) · excluded {{ $show->analytics_unavailable_at->diffForHumans() }}@endif
                        </div>
                        @if($blocking !== [])
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach($blocking as $label => $count)
                                    <span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $count }} {{ $label }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="shrink-0">
                        @if($blocking === [])
                            <button
                                wire:click="deleteShow({{ $show->id }})"
                                wire:confirm="Permanently delete &quot;{{ addslashes($show->title) }}&quot;? This cannot be undone."
                                class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                            >
                                <x-heroicon-o-trash class="h-3.5 w-3.5" /> Delete
                            </button>
                        @else
                            <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-400 dark:border-gray-700">Has related data</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-sm text-gray-500">No confirmed no-shows found. Any that come up during a future analytics sync will land here.</div>
            @endforelse
        </div>
    </section>
</div>
</x-filament-panels::page>
