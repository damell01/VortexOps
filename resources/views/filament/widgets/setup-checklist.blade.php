<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <span class="flex items-center gap-2">
                <x-heroicon-o-rocket-launch class="h-5 w-5 text-primary-500" />
                Get started with VortexOps
            </span>
        </x-slot>
        <x-slot name="description">Finish setting up your workspace — {{ $doneCount }} of {{ $total }} done.</x-slot>
        <x-slot name="headerEnd">
            <x-filament::button
                size="xs"
                color="gray"
                wire:click="dismiss"
                wire:confirm="Hide the setup checklist? You can still reach these pages from the sidebar.">
                Dismiss
            </x-filament::button>
        </x-slot>

        {{-- progress bar --}}
        <div class="mb-4 h-2 w-full rounded-full bg-gray-100 dark:bg-white/10 overflow-hidden">
            <div class="h-full rounded-full bg-primary-500 transition-all"
                 style="width: {{ $total > 0 ? round($doneCount / $total * 100) : 0 }}%"></div>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($steps as $step)
                <div class="flex items-center gap-3 py-3">
                    @if ($step['done'])
                        <x-heroicon-s-check-circle class="h-6 w-6 shrink-0 text-emerald-500" />
                    @else
                        <x-heroicon-o-arrow-right-circle class="h-6 w-6 shrink-0 text-gray-300 dark:text-gray-600" />
                    @endif
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium {{ $step['done'] ? 'text-gray-400 dark:text-gray-500 line-through' : 'text-gray-900 dark:text-gray-100' }}">
                            {{ $step['label'] }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500">{{ $step['hint'] }}</p>
                    </div>
                    @unless ($step['done'])
                        <a href="{{ $step['url'] }}" wire:navigate
                           class="shrink-0 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                            Set up →
                        </a>
                    @endunless
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
