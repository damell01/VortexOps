<x-filament-panels::page>
    @php
        $stats = $this->stats;
        $insights = $this->insights;
        $tasks = $this->recentTasks;
        $severityStyles = [
            'high' => 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/30',
            'medium' => 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30',
            'low' => 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/30',
            'info' => 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900',
        ];
    @endphp

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-lg font-semibold text-gray-950 dark:text-white">Background AI, not page-load AI</div>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                        VortexOps computes business facts with PHP/SQL, queues AI work to the dedicated <code>ai</code> worker, and stores the result here. Opening inventory, shows, payroll, receiving, or this page never waits for Ollama.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button wire:click="runAnalysis('operations')" class="min-h-11 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">Run Ops Summary</button>
                    <button wire:click="runAnalysis('exceptions')" class="min-h-11 rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Scan Exceptions</button>
                    <button wire:click="runAnalysis('cleanup')" class="min-h-11 rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Scan Data Cleanup</button>
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
            @foreach([
                ['Open', $stats['open']],
                ['High Priority', $stats['high']],
                ['Inventory', $stats['inventory']],
                ['Payroll', $stats['payroll']],
                ['Shows / Imports', $stats['shows']],
                ['Cleanup', $stats['cleanup']],
                ['AI Queue', $stats['pending_ai']],
            ] as [$label, $value])
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($value) }}</div>
                </div>
            @endforeach
        </div>

        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach([
                'all' => 'All',
                'inventory' => 'Inventory',
                'imports' => 'Imports',
                'shows' => 'Shows',
                'payroll' => 'Payroll',
                'streamers' => 'Streamers',
                'cleanup' => 'Cleanup',
                'exceptions' => 'Exceptions',
                'management' => 'Management',
                'operations' => 'Operations',
            ] as $key => $label)
                <button wire:click="setCategory('{{ $key }}')"
                    class="min-h-10 whitespace-nowrap rounded-full border px-4 py-2 text-sm font-medium {{ $category === $key ? 'border-primary-600 bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300' : 'border-gray-200 bg-white text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Needs attention</h2>
                    <span class="text-xs text-gray-500">Human review only — AI never changes stock/payroll automatically</span>
                </div>

                @forelse($insights as $insight)
                    <div class="rounded-xl border p-4 {{ $severityStyles[$insight['severity']] ?? $severityStyles['info'] }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-black/5 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ str_replace('_', ' ', $insight['category']) }}</span>
                                    <span class="text-[11px] font-semibold uppercase tracking-wide {{ $insight['severity'] === 'high' ? 'text-red-700 dark:text-red-300' : ($insight['severity'] === 'medium' ? 'text-amber-700 dark:text-amber-300' : 'text-gray-500') }}">{{ $insight['severity'] }}</span>
                                    @if($insight['generated_at'])<span class="text-xs text-gray-500">{{ $insight['generated_at'] }}</span>@endif
                                </div>
                                <h3 class="mt-2 text-sm font-semibold text-gray-950 dark:text-white">{{ $insight['title'] }}</h3>
                                <p class="mt-1 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $insight['summary'] }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button wire:click="markReviewed({{ $insight['id'] }})" class="min-h-10 rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">Reviewed</button>
                                <button wire:click="dismissInsight({{ $insight['id'] }})" class="min-h-10 rounded-lg px-3 text-xs font-semibold text-gray-500 hover:bg-black/5 dark:hover:bg-white/5">Dismiss</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">No open insights in this category</div>
                        <p class="mt-1 text-sm text-gray-500">Run a background scan above or wait for the scheduled jobs.</p>
                    </div>
                @endforelse
            </div>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Recent AI jobs</h2>
                        <span class="text-xs text-gray-500">queue: ai</span>
                    </div>
                    <div class="space-y-3">
                        @forelse($tasks as $task)
                            <div class="border-b border-gray-100 pb-3 last:border-0 last:pb-0 dark:border-gray-800">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $task['type'] }}</div>
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold {{ $task['status'] === 'completed' ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300' : ($task['status'] === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300') }}">{{ $task['status'] }}</span>
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    #{{ $task['id'] }} · {{ $task['created_at'] }}
                                    @if($task['duration'] !== null) · {{ $task['duration'] }}s @endif
                                    @if($task['insights_stored'] !== null) · {{ $task['insights_stored'] }} insight(s) @endif
                                </div>
                                @if($task['ai_available'] === false)
                                    <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">Ollama unavailable; deterministic checks still completed.</div>
                                @endif
                                @if($task['error'])
                                    <div class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $task['error'] }}</div>
                                @endif
                            </div>
                        @empty
                            <div class="text-sm text-gray-500">No background operations jobs yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Background tools</h2>
                    <div class="mt-3 grid gap-2">
                        <button wire:click="runAnalysis('inventory')" class="min-h-11 rounded-lg border border-gray-200 px-3 text-left text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Inventory health + discrepancies</button>
                        <button wire:click="runAnalysis('payroll')" class="min-h-11 rounded-lg border border-gray-200 px-3 text-left text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Payroll blockers + review</button>
                        <button wire:click="runAnalysis('streamers')" class="min-h-11 rounded-lg border border-gray-200 px-3 text-left text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Streamer performance summary</button>
                        <button wire:click="runAnalysis('weekly')" class="min-h-11 rounded-lg border border-gray-200 px-3 text-left text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Weekly management summary</button>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs leading-5 text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                    Manifest / packing-slip extraction remains on the same dedicated AI queue. Matching first uses exact identifiers and deterministic rules; AI suggestions require review before receiving lines create or change inventory.
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
