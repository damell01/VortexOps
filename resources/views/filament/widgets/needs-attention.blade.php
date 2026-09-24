<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-4 dark:border-gray-800 sm:px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 text-rose-600 dark:bg-rose-950/30"><x-heroicon-o-exclamation-triangle class="h-5 w-5" /></div>
            <div><h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Needs Attention</h2><p class="text-[11px] text-gray-500 sm:text-xs">Items that need action or review.</p></div>
        </div>
        <div class="px-4 sm:px-5">

        @if (empty($items))
            <div class="flex items-center gap-3 py-6 justify-center text-sm text-gray-500 dark:text-gray-400">
                <x-heroicon-o-check-circle class="h-5 w-5 text-emerald-500" />
                You're all caught up — nothing needs your attention.
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
                    <a href="{{ $item['url'] }}" wire:navigate
                       class="flex items-center gap-3 py-3 -mx-2 px-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5 transition">
                        <x-dynamic-component :component="$item['icon']" class="h-5 w-5 shrink-0 {{ $tone }}" />
                        <span class="inline-flex items-center justify-center min-w-7 h-6 px-2 rounded-full text-xs font-bold {{ $badge }}">
                            {{ number_format($item['count']) }}
                        </span>
                        <span class="text-sm text-gray-800 dark:text-gray-100 flex-1 min-w-0">{{ $item['label'] }}</span>
                        <x-heroicon-m-chevron-right class="h-4 w-4 text-gray-300 dark:text-gray-600 shrink-0" />
                    </a>
                @endforeach
            </div>
        @endif
        </div>
    </section>
</x-filament-widgets::widget>
