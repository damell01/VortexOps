<x-filament-panels::page>
    @php
        $levelColors = [
            'emergency' => ['bg' => 'bg-red-600',    'text' => 'text-red-600 dark:text-red-400',    'badge' => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'],
            'alert'     => ['bg' => 'bg-red-500',    'text' => 'text-red-500 dark:text-red-400',    'badge' => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'],
            'critical'  => ['bg' => 'bg-red-500',    'text' => 'text-red-500 dark:text-red-400',    'badge' => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'],
            'error'     => ['bg' => 'bg-red-500',    'text' => 'text-red-500 dark:text-red-400',    'badge' => 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'],
            'warning'   => ['bg' => 'bg-amber-400',  'text' => 'text-amber-600 dark:text-amber-400','badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'],
            'notice'    => ['bg' => 'bg-sky-400',    'text' => 'text-sky-600 dark:text-sky-400',    'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300'],
            'info'      => ['bg' => 'bg-sky-400',    'text' => 'text-sky-600 dark:text-sky-400',    'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300'],
            'debug'     => ['bg' => 'bg-gray-400',   'text' => 'text-gray-500 dark:text-gray-400',  'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'],
        ];
        $defaultColor = ['bg' => 'bg-gray-400', 'text' => 'text-gray-500', 'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'];

        $entries    = $this->entries;
        $files      = $this->logFiles();
        $levelCounts = $this->levelCounts;
    @endphp

    <div class="space-y-4">

        {{-- Toolbar --}}
        <div class="flex flex-wrap items-center gap-3">
            {{-- File picker --}}
            @if (count($files) > 1)
                <select
                    wire:model.live="selectedFile"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                >
                    @foreach ($files as $file)
                        <option value="{{ $file }}">{{ $file }}</option>
                    @endforeach
                </select>
            @else
                <span class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    {{ $selectedFile ?: 'laravel.log' }}
                </span>
            @endif

            {{-- Level filter --}}
            <select
                wire:model.live="levelFilter"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
            >
                <option value="">All levels</option>
                @foreach (['error', 'warning', 'info', 'debug', 'critical'] as $lvl)
                    <option value="{{ $lvl }}">{{ ucfirst($lvl) }} ({{ $levelCounts[$lvl] ?? 0 }})</option>
                @endforeach
            </select>

            {{-- Search --}}
            <div class="relative flex-1 min-w-[200px]">
                <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search messages…"
                    class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-700 shadow-sm placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                />
            </div>

            <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto">
                Showing {{ count($entries) }} entries (newest first)
            </span>
        </div>

        {{-- Summary badges --}}
        <div class="flex flex-wrap gap-2">
            @foreach (['error' => 'Errors', 'warning' => 'Warnings', 'info' => 'Info', 'debug' => 'Debug'] as $lvl => $label)
                @php $count = $levelCounts[$lvl] ?? 0; @endphp
                @if ($count > 0)
                    <button
                        wire:click="$set('levelFilter', '{{ $levelFilter === $lvl ? '' : $lvl }}')"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold transition
                            {{ $levelFilter === $lvl ? 'ring-2 ring-offset-1 ring-current' : '' }}
                            {{ ($levelColors[$lvl] ?? $defaultColor)['badge'] }}"
                    >
                        {{ $count }} {{ $label }}
                    </button>
                @endif
            @endforeach
        </div>

        {{-- Log entries --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
            @forelse ($entries as $i => $entry)
                @php $colors = $levelColors[$entry['level']] ?? $defaultColor; @endphp
                <div
                    x-data="{ open: false }"
                    class="border-b border-gray-100 dark:border-gray-800 last:border-0"
                >
                    <button
                        @click="open = !open"
                        class="w-full flex items-start gap-3 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                    >
                        {{-- Level indicator bar --}}
                        <div class="mt-1 h-3.5 w-1 rounded-full flex-shrink-0 {{ $colors['bg'] }}"></div>

                        {{-- Level badge --}}
                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider flex-shrink-0 {{ $colors['badge'] }} w-16 justify-center">
                            {{ $entry['level'] }}
                        </span>

                        {{-- Timestamp --}}
                        <span class="text-xs text-gray-400 dark:text-gray-500 flex-shrink-0 w-36 font-mono">
                            {{ \Illuminate\Support\Carbon::parse($entry['timestamp'])->format('M j, H:i:s') }}
                        </span>

                        {{-- Message preview --}}
                        <span class="flex-1 text-sm text-gray-700 dark:text-gray-300 truncate {{ $colors['text'] }}">
                            {{ Str::limit(strtok($entry['message'], "\n"), 140) }}
                        </span>

                        <x-heroicon-o-chevron-down class="h-4 w-4 text-gray-400 flex-shrink-0 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                    </button>

                    {{-- Expanded detail --}}
                    <div x-show="open" x-collapse class="px-4 pb-4">
                        <pre class="mt-1 ml-8 overflow-x-auto rounded-lg bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-700 p-3 text-xs font-mono text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words">{{ $entry['message'] }}</pre>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-500">
                    <x-heroicon-o-check-circle class="h-10 w-10 mb-2 text-emerald-400" />
                    <p class="text-sm font-medium">No log entries</p>
                    <p class="text-xs mt-1">{{ $levelFilter ? 'No ' . $levelFilter . ' entries found' : 'The log file is empty' }}</p>
                </div>
            @endforelse
        </div>

        @if (count($entries) >= $perPage)
            <div class="text-center">
                <button
                    wire:click="$set('perPage', {{ $perPage + 100 }})"
                    class="text-sm text-primary-600 hover:underline dark:text-primary-400"
                >
                    Load more entries
                </button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
