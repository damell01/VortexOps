<x-filament-panels::page>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @php
            $moduleIcons = [
                'streams'    => '🎬',
                'payouts'    => '💰',
                'inventory'  => '📦',
                'purchasing' => '🛒',
                'operations' => '⚙️',
                'reporting'  => '📊',
                'ai'         => '✨',
                'timekeeping'=> '⏱️',
            ];
        @endphp

        @foreach (\App\Support\AdminModules::enabledSlugs() as $slug)
            @php $def = \App\Support\AdminModules::definitions()[$slug] ?? null; @endphp
            @if ($def)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <div class="text-2xl leading-none mt-0.5 flex-shrink-0">
                            {{ $moduleIcons[$slug] ?? '📋' }}
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white text-sm mb-1">
                                {{ $def['label'] }}
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                                {{ $def['description'] }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</x-filament-panels::page>
