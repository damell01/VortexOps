@php
    $summary  = $this->summary;
    $byTask   = $this->byTask;
    $byModel  = $this->byModel;
    $failures = $this->recentFailures;
    $learning = $this->learning;
@endphp

<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Window selector --}}
        <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Window:</span>
            @foreach ([1 => '24h', 7 => '7 days', 30 => '30 days'] as $d => $label)
                <button type="button" wire:click="setDays({{ $d }})"
                    class="rounded-lg px-3 py-1 text-xs font-medium transition-colors
                        {{ $days === $d
                            ? 'bg-violet-600 text-white'
                            : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- KPI row --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $kpis = [
                    ['label' => 'AI calls',      'value' => number_format($summary['calls']),        'sub' => 'in window'],
                    ['label' => 'Success rate',  'value' => $summary['success_rate'] . '%',           'sub' => $summary['failed'] . ' failed'],
                    ['label' => 'Avg latency',   'value' => number_format($summary['avg_latency']) . ' ms', 'sub' => 'per call'],
                    ['label' => 'Aliases learned','value' => number_format($learning['aliases']),      'sub' => $learning['confirmations'] . ' confirmations'],
                ];
            @endphp
            @foreach ($kpis as $kpi)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $kpi['value'] }}</p>
                    <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $kpi['sub'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- By task / by model --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach ([['By Task', 'task', $byTask], ['By Model', 'model', $byModel]] as [$heading, $key, $rows])
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                    <div class="border-b border-gray-100 dark:border-gray-800 px-5 py-3">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $heading }}</h3>
                    </div>
                    @if (empty($rows))
                        <p class="px-5 py-6 text-sm text-gray-400 text-center">No calls recorded yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-gray-400">
                                        <th class="px-5 py-2 font-medium">{{ ucfirst($key) }}</th>
                                        <th class="px-3 py-2 font-medium text-right">Calls</th>
                                        <th class="px-3 py-2 font-medium text-right">Success</th>
                                        <th class="px-5 py-2 font-medium text-right">Avg ms</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                                    @foreach ($rows as $row)
                                        <tr>
                                            <td class="px-5 py-2 font-medium text-gray-800 dark:text-gray-200">{{ $row[$key] }}</td>
                                            <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ number_format($row['calls']) }}</td>
                                            <td class="px-3 py-2 text-right {{ $row['success_rate'] >= 95 ? 'text-green-600 dark:text-green-400' : ($row['success_rate'] >= 80 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ $row['success_rate'] }}%</td>
                                            <td class="px-5 py-2 text-right text-gray-600 dark:text-gray-300">{{ number_format($row['avg_latency']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Recent failures --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="border-b border-gray-100 dark:border-gray-800 px-5 py-3">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent Failures</h3>
            </div>
            @if (empty($failures))
                <p class="px-5 py-6 text-sm text-gray-400 text-center">No failures recorded — nice.</p>
            @else
                <div class="divide-y divide-gray-50 dark:divide-gray-800">
                    @foreach ($failures as $f)
                        <div class="px-5 py-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $f['task'] }} · {{ $f['model'] }}</span>
                                <span class="text-[11px] text-gray-400">{{ $f['created_at'] }}</span>
                            </div>
                            <p class="text-xs text-red-600 dark:text-red-400 break-words mt-0.5">{{ $f['error'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- What the matcher has learned --}}
        @if (! empty($learning['top_aliases']))
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="border-b border-gray-100 dark:border-gray-800 px-5 py-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Most-Confirmed Aliases</h3>
                    <p class="text-xs text-gray-400">Vendor descriptions the matcher now recognizes instantly.</p>
                </div>
                <div class="divide-y divide-gray-50 dark:divide-gray-800">
                    @foreach ($learning['top_aliases'] as $alias)
                        <div class="flex items-center justify-between px-5 py-2">
                            <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $alias['value'] }}</span>
                            <span class="text-xs text-gray-400 flex-shrink-0 ml-3">confirmed {{ $alias['times_confirmed'] }}×</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
