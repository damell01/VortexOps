<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Pipeline Status</x-slot>

        <div class="flex flex-wrap items-center gap-x-2 gap-y-4">
            @foreach ($this->record->pipelineSteps() as $i => $step)
                <div class="flex items-center gap-2">
                    <div @class([
                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                        'bg-success-500 text-white' => $step['status'] === 'done',
                        'bg-primary-500 text-white ring-4 ring-primary-500/20' => $step['status'] === 'current',
                        'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => $step['status'] === 'pending',
                        'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-600' => $step['status'] === 'skipped',
                    ])>
                        @if ($step['status'] === 'done')
                            <x-heroicon-o-check class="h-4 w-4" />
                        @else
                            {{ $i + 1 }}
                        @endif
                    </div>
                    <div class="flex flex-col">
                        <span @class([
                            'text-sm font-medium',
                            'text-gray-950 dark:text-white' => in_array($step['status'], ['done', 'current']),
                            'text-gray-400 dark:text-gray-500' => in_array($step['status'], ['pending', 'skipped']),
                        ])>{{ $step['label'] }}</span>
                        @if ($step['note'])
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $step['note'] }}</span>
                        @endif
                    </div>
                </div>
                @if (! $loop->last)
                    <div class="hidden h-px w-8 shrink-0 bg-gray-200 dark:bg-gray-700 sm:block"></div>
                @endif
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
