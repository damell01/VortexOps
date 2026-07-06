<x-filament-panels::page>
@php
    $db        = $this->dbStatus;
    $queue     = $this->queueStatus;
    $ollama    = $this->ollamaStatus;
    $scheduler = $this->schedulerStatus;
    $storage   = $this->storageStatus;
    $failed    = $this->failedJobs;
@endphp

<div class="space-y-6" wire:poll.30000ms>

    {{-- ── Status Cards Grid ───────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

        {{-- Database --}}
        <div class="rounded-xl border {{ $db['ok'] ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950' : 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950' }} px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="rounded-full {{ $db['ok'] ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900' }} p-2">
                    <x-heroicon-o-circle-stack class="h-5 w-5 {{ $db['ok'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                </div>
                <div>
                    <p class="text-sm font-semibold {{ $db['ok'] ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100' }}">Database</p>
                    <p class="text-xs {{ $db['ok'] ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">{{ $db['label'] }}</p>
                </div>
                <div class="ml-auto">
                    @if ($db['ok'])
                        <span class="relative flex h-3 w-3">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full bg-green-500"></span>
                        </span>
                    @else
                        <span class="h-3 w-3 rounded-full bg-red-500 block"></span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Ollama --}}
        <div class="rounded-xl border {{ $ollama['ok'] ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950' : 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950' }} px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="rounded-full {{ $ollama['ok'] ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900' }} p-2">
                    <x-heroicon-o-sparkles class="h-5 w-5 {{ $ollama['ok'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}" />
                </div>
                <div class="min-w-0">
                    <p class="text-sm font-semibold {{ $ollama['ok'] ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100' }}">Ollama AI</p>
                    <p class="text-xs truncate {{ $ollama['ok'] ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                        {{ $ollama['ok'] ? (count($ollama['models']) . ' model(s) loaded') : $ollama['label'] }}
                    </p>
                    @if ($ollama['ok'] && count($ollama['models']) > 0)
                        <p class="text-[10px] text-gray-400 truncate mt-0.5">{{ implode(', ', $ollama['models']) }}</p>
                    @elseif (! $ollama['ok'])
                        <p class="text-[10px] text-gray-400 truncate mt-0.5">{{ $ollama['url'] }}</p>
                    @endif
                </div>
                <div class="ml-auto flex-shrink-0">
                    @if ($ollama['ok'])
                        <span class="relative flex h-3 w-3">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex h-3 w-3 rounded-full bg-green-500"></span>
                        </span>
                    @else
                        <span class="h-3 w-3 rounded-full bg-red-500 block"></span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Scheduler --}}
        @php $schedulerOk = $scheduler['ok'] === true; $schedulerUnknown = $scheduler['ok'] === null; @endphp
        <div class="rounded-xl border {{ $schedulerOk ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950' : ($schedulerUnknown ? 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900' : 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950') }} px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="rounded-full {{ $schedulerOk ? 'bg-green-100 dark:bg-green-900' : ($schedulerUnknown ? 'bg-gray-100 dark:bg-gray-800' : 'bg-amber-100 dark:bg-amber-900') }} p-2">
                    <x-heroicon-o-clock class="h-5 w-5 {{ $schedulerOk ? 'text-green-600 dark:text-green-400' : ($schedulerUnknown ? 'text-gray-400' : 'text-amber-600 dark:text-amber-400') }}" />
                </div>
                <div>
                    <p class="text-sm font-semibold {{ $schedulerOk ? 'text-green-900 dark:text-green-100' : ($schedulerUnknown ? 'text-gray-700 dark:text-gray-300' : 'text-amber-900 dark:text-amber-100') }}">Scheduler</p>
                    <p class="text-xs {{ $schedulerOk ? 'text-green-600 dark:text-green-400' : ($schedulerUnknown ? 'text-gray-500' : 'text-amber-600 dark:text-amber-400') }}">{{ $scheduler['label'] }}</p>
                    @if ($schedulerUnknown)
                        <p class="text-[10px] text-gray-400 mt-0.5">Run: <code class="font-mono">php artisan schedule:run</code></p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Queue Worker (Default) --}}
        @php $queueOk = $queue['ok'] && $queue['failed'] === 0; @endphp
        <div class="rounded-xl border {{ $queueOk ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950' : 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950' }} px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="rounded-full {{ $queueOk ? 'bg-green-100 dark:bg-green-900' : 'bg-amber-100 dark:bg-amber-900' }} p-2">
                    <x-heroicon-o-queue-list class="h-5 w-5 {{ $queueOk ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}" />
                </div>
                <div>
                    <p class="text-sm font-semibold {{ $queueOk ? 'text-green-900 dark:text-green-100' : 'text-amber-900 dark:text-amber-100' }}">Queue Worker</p>
                    <p class="text-xs {{ $queueOk ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ $queue['default_pending'] }} pending · {{ $queue['active'] }} active
                    </p>
                    @if ($queue['failed'] > 0)
                        <p class="text-[10px] text-red-500 font-semibold mt-0.5">{{ $queue['failed'] }} failed job(s)</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- AI Queue Worker --}}
        <div class="rounded-xl border {{ $queue['ai_pending'] > 5 ? 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950' : 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950' }} px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="rounded-full {{ $queue['ai_pending'] > 5 ? 'bg-amber-100 dark:bg-amber-900' : 'bg-green-100 dark:bg-green-900' }} p-2">
                    <x-heroicon-o-cpu-chip class="h-5 w-5 {{ $queue['ai_pending'] > 5 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}" />
                </div>
                <div>
                    <p class="text-sm font-semibold {{ $queue['ai_pending'] > 5 ? 'text-amber-900 dark:text-amber-100' : 'text-green-900 dark:text-green-100' }}">AI Worker</p>
                    <p class="text-xs {{ $queue['ai_pending'] > 5 ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ $queue['ai_pending'] }} pending in AI queue
                    </p>
                </div>
            </div>
        </div>

        {{-- Storage --}}
        <div class="rounded-xl border {{ ($storage['ok'] ?? true) ? 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' : 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950' }} px-5 py-4">
            <div class="flex items-center gap-3">
                <div class="rounded-full {{ ($storage['ok'] ?? true) ? 'bg-gray-100 dark:bg-gray-800' : 'bg-amber-100 dark:bg-amber-900' }} p-2">
                    <x-heroicon-o-server class="h-5 w-5 {{ ($storage['ok'] ?? true) ? 'text-gray-500 dark:text-gray-400' : 'text-amber-600' }}" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Disk Storage</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $storage['used'] }} used / {{ $storage['total'] }}</p>
                    <div class="mt-1.5 h-1.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                        <div
                            class="h-1.5 rounded-full {{ $storage['pct'] >= 90 ? 'bg-red-500' : ($storage['pct'] >= 75 ? 'bg-amber-500' : 'bg-green-500') }}"
                            style="width: {{ min(100, $storage['pct']) }}%"
                        ></div>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-0.5">{{ $storage['pct'] }}% used · {{ $storage['free'] }} free</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Setup Reminders ─────────────────────────────────────────────────── --}}
    @if (! $ollama['ok'] || $scheduler['ok'] === null || $scheduler['ok'] === false)
        <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950 px-5 py-4 space-y-3 text-sm">
            <p class="font-semibold text-blue-900 dark:text-blue-100">Setup Checklist</p>
            <ul class="space-y-2 text-blue-700 dark:text-blue-300">
                @if (! $ollama['ok'])
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-exclamation-circle class="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-500" />
                        <span>
                            <strong>Ollama offline.</strong> Install Ollama and run:
                            <code class="rounded bg-blue-100 dark:bg-blue-900 px-1 font-mono text-xs">ollama serve</code>
                            then configure the URL in
                            <a href="{{ route('filament.admin.pages.app-settings') }}" class="underline">Settings → Ollama URL</a>
                            (use <code class="font-mono text-xs">http://127.0.0.1:11434</code> for bare-metal).
                        </span>
                    </li>
                @endif
                @if ($scheduler['ok'] === null)
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-exclamation-circle class="mt-0.5 h-4 w-4 flex-shrink-0 text-amber-500" />
                        <span>
                            <strong>Scheduler has never run.</strong> Add to crontab:
                            <code class="rounded bg-blue-100 dark:bg-blue-900 px-1 font-mono text-xs">* * * * * cd /var/www/vortexops && php artisan schedule:run >> /dev/null 2>&1</code>
                        </span>
                    </li>
                @elseif ($scheduler['ok'] === false)
                    <li class="flex items-start gap-2">
                        <x-heroicon-o-exclamation-circle class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-500" />
                        <span><strong>Scheduler may be down.</strong> Check your crontab or scheduler service.</span>
                    </li>
                @endif
            </ul>
        </div>
    @endif

    {{-- ── Failed Jobs ─────────────────────────────────────────────────────── --}}
    @if (count($failed) > 0)
        <div class="rounded-xl border border-red-200 dark:border-red-800 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-red-100 dark:border-red-900 bg-red-50 dark:bg-red-950">
                <h3 class="text-sm font-semibold text-red-900 dark:text-red-100">Failed Jobs ({{ count($failed) }})</h3>
                <button
                    wire:click="clearFailedJobs"
                    wire:confirm="Clear all failed jobs?"
                    class="text-xs text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 font-medium"
                >
                    Clear All
                </button>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($failed as $job)
                    <div class="px-5 py-3 space-y-1">
                        <div class="flex items-center gap-2 text-sm">
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $job['job'] }}</span>
                            <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs text-gray-500">{{ $job['queue'] }}</span>
                            <span class="ml-auto text-xs text-gray-400">{{ \Carbon\Carbon::parse($job['failed_at'])->diffForHumans() }}</span>
                        </div>
                        <p class="text-xs text-red-500 font-mono truncate">{{ $job['exception'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
            <x-heroicon-o-check-circle class="h-6 w-6 mx-auto mb-1 text-green-500" />
            No failed jobs.
        </div>
    @endif

    {{-- ── Queue Metrics ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $queue['default_pending'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Default Queue</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 text-center">
            <p class="text-2xl font-bold text-violet-600 dark:text-violet-400">{{ $queue['ai_pending'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">AI Queue</p>
        </div>
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $queue['active'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Active (last 10 min)</p>
        </div>
        <div class="rounded-xl border {{ $queue['failed'] > 0 ? 'border-red-200 dark:border-red-800' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-900 px-4 py-3 text-center">
            <p class="text-2xl font-bold {{ $queue['failed'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">{{ $queue['failed'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Failed Jobs</p>
        </div>
    </div>

    <p class="text-xs text-gray-400 text-right">Auto-refreshes every 30 s</p>
</div>
</x-filament-panels::page>
