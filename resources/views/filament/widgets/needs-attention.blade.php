<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/30"><x-heroicon-o-exclamation-triangle class="h-5 w-5" /></div>
            <div><h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Needs Attention</h2><p class="text-[11px] text-gray-500 sm:text-xs">Items that need action or review.</p></div>
        </div>
        <div class="px-4 sm:px-5">

        @if (empty($items))
            @php
                // Eevee cheers through the working day; Snorlax naps evenings and weekends.
                $now = now(config('app.timezone'));
                $napping = $now->isWeekend() || $now->hour >= 18 || $now->hour < 6;
            @endphp
            <div @class(['vx-na-clear', 'is-sleepy' => $napping]) data-vx-na-clear>
                <img src="/img/pokemon/{{ $napping ? 'snorlax' : 'eevee' }}.gif" alt="" aria-hidden="true" class="vx-sprite">
                @if ($napping)<span class="vx-na-zzz" aria-hidden="true">z<i>z</i><b>z</b></span>@endif
                <p class="text-sm font-semibold text-gray-900 dark:text-white">You're all caught up!</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $napping ? 'Nothing needs you, so Snorlax is taking a nap.' : 'Nothing needs your attention. Eevee approves.' }}</p>
            </div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($items as $item)
                    @php
                        $tone = match ($item['color']) {
                            'danger'  => 'text-rose-500',
                            'warning' => 'text-amber-500',
                            'info'    => 'text-sky-500',
                            default   => 'text-gray-400',
                        };
                        $badge = match ($item['color']) {
                            'danger'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                            'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                            'info'    => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                            default   => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300',
                        };
                    @endphp
                    @php
                        $rowClass = 'flex w-full items-center gap-3 py-3 -mx-2 px-2 rounded-lg text-left hover:bg-gray-50 dark:hover:bg-white/5 transition';
                    @endphp
                    @if ($item['detail'] ?? null)
                        {{-- Opens the list behind the count instead of leaving the dashboard. --}}
                        <button type="button" wire:click="openDetail('{{ $item['detail'] }}')" wire:loading.attr="disabled" class="{{ $rowClass }}" aria-haspopup="dialog">
                    @else
                        <a href="{{ $item['url'] }}" wire:navigate class="{{ $rowClass }}">
                    @endif
                        <x-dynamic-component :component="$item['icon']" class="h-5 w-5 shrink-0 {{ $tone }}" />
                        <span class="inline-flex items-center justify-center min-w-7 h-6 px-2 rounded-full text-xs font-bold {{ $badge }}">
                            {{ number_format($item['count']) }}
                        </span>
                        <span class="text-sm text-gray-800 dark:text-gray-100 flex-1 min-w-0">{{ $item['label'] }}</span>
                        <x-heroicon-m-chevron-right class="h-4 w-4 text-gray-300 dark:text-gray-600 shrink-0" />
                    @if ($item['detail'] ?? null)
                        </button>
                    @else
                        </a>
                    @endif
                @endforeach
            </div>
        @endif
        </div>
    </section>

    <x-filament::modal :id="$this->detailModalId()" width="4xl">
        @if ($detailData)
            <x-slot name="heading">{{ $detailData['title'] }}</x-slot>
            <x-slot name="description">{{ $detailData['description'] }}</x-slot>
            <div class="-mx-2 max-h-[60vh] overflow-auto">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-white text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900">
                        <tr>@foreach ($detailData['columns'] as $i => $column)<th class="px-2 py-2 font-semibold {{ $i > 0 ? 'whitespace-nowrap' : '' }}">{{ $column }}</th>@endforeach</tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($detailData['rows'] as $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                @foreach ($row['cells'] as $i => $cell)
                                    @php
                                        $link = $row['links'][$i] ?? ($i === 0 ? $row['url'] : null);
                                    @endphp
                                    <td class="px-2 py-2 align-top {{ $i === 0 ? 'font-medium text-gray-900 dark:text-white' : 'tabular-nums text-gray-600 dark:text-gray-300' }}">
                                        @if ($link)<a href="{{ $link }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $cell }}</a>@else{{ $cell }}@endif
                                    </td>
                                @endforeach
                                @if (! empty($row['action']))
                                    <td class="px-2 py-2 text-right align-top">
                                        <x-filament::button size="xs" color="gray" wire:click="{{ $row['action']['method'] }}('{{ $row['action']['arg'] }}')" wire:loading.attr="disabled">{{ $row['action']['label'] }}</x-filament::button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($detailData['columns']) }}" class="px-2 py-6 text-center text-gray-500">Nothing here any more.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-slot name="footer">
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <span class="text-gray-500">
                        Showing {{ count($detailData['rows']) }} of {{ number_format($detailData['total']) }}
                    </span>
                    <x-filament::button tag="a" :href="$detailData['more_url']" color="gray" icon="heroicon-o-arrow-top-right-on-square">{{ $detailData['more_label'] }}</x-filament::button>
                </div>
            </x-slot>
        @else
            <x-slot name="heading">Details</x-slot>
            <p class="text-sm text-gray-500">These details could not be loaded. Use the full page instead.</p>
        @endif
    </x-filament::modal>
</x-filament-widgets::widget>
