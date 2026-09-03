<x-filament-panels::page>
    @php
        $entry   = $this->openEntry;
        $stats   = $this->stats;
        $entries = $this->entries;
        $team    = $this->teamHours;
        $isIn    = $this->isClockedIn;
        $user    = auth()->user();
        $seesTeam = ($user?->isOwner() || $user?->isAdmin()) ?? false;

        $periodOptions = [
            'this_week'  => 'This Week',
            'last_week'  => 'Last Week',
            'pay_period' => 'Current Pay Period',
            'this_month' => 'This Month',
            'custom'     => 'Custom Range',
        ];
    @endphp

    <div class="space-y-6 max-w-4xl">

        {{-- ── Timezone Notice ──────────────────────────────────────────────── --}}
        @php
            $userTz = $this->userTimezone();
            $isDefaultTz = $userTz === 'UTC';
        @endphp
        @if ($isDefaultTz)
            <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-4 py-3 flex items-start gap-3"
                 x-data="{ detectingTz: false }">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                <div class="flex-1">
                    <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">Timezone not set</p>
                    <p class="text-xs text-amber-800 dark:text-amber-200 mt-0.5">Your times are showing in UTC. Click below to auto-detect and save your timezone, or <a href="{{ route('filament.admin.pages.edit-profile') }}" class="underline font-medium hover:no-underline">update it manually</a>.</p>
                </div>
                <button
                    type="button"
                    @click="detectingTz = true; $wire.saveDetectedTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)"
                    :disabled="detectingTz"
                    class="shrink-0 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-500 transition-colors disabled:opacity-60">
                    <span x-show="!detectingTz"><x-heroicon-o-arrow-path class="h-3.5 w-3.5" /></span>
                    <span x-show="detectingTz"><svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></span>
                    <span x-text="detectingTz ? 'Saving...' : 'Auto-detect'"></span>
                </button>
            </div>
        @endif

        {{-- ── Clock In / Out Card ──────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            <div class="px-6 py-5 flex flex-col sm:flex-row sm:items-center gap-4">

                {{-- Status indicator --}}
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div class="relative shrink-0">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center {{ $isIn ? 'bg-emerald-100 dark:bg-emerald-900/40' : 'bg-gray-100 dark:bg-gray-800' }}">
                            <x-heroicon-o-clock class="h-6 w-6 {{ $isIn ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" />
                        </div>
                        @if ($isIn)
                            <span class="absolute top-0 right-0 w-3 h-3 rounded-full bg-emerald-500 border-2 border-white dark:border-gray-900"></span>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $isIn ? 'Currently clocked in' : 'Not clocked in' }}
                        </p>
                        @if ($isIn && $entry)
                            <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5">
                                Since {{ $this->formatTimeInUserTz($entry->clocked_in_at) }}
                                @if ($stats['in_progress'] > 0)
                                    &mdash; {{ \App\Models\TimeEntry::formatMinutes($stats['in_progress']) }} elapsed
                                @endif
                            </p>
                            @if ($entry->notes)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate max-w-xs">{{ $entry->notes }}</p>
                            @endif
                        @else
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Add an optional note before clocking in</p>
                        @endif
                    </div>
                </div>

                {{-- Note input + button --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 shrink-0">
                    @if (! $isIn)
                        <input
                            wire:model.blur="note"
                            type="text"
                            placeholder="What are you working on? (optional)"
                            maxlength="255"
                            class="flex-1 sm:w-56 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none"
                        />
                    @endif

                    @if ($isIn)
                        <button
                            wire:click="clockOut"
                            wire:loading.attr="disabled"
                            wire:target="clockOut"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-60 transition-colors"
                        >
                            <span wire:loading.remove wire:target="clockOut"><x-heroicon-o-stop-circle class="h-4 w-4" /></span>
                            <span wire:loading wire:target="clockOut">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </span>
                            <span wire:loading.remove wire:target="clockOut">Clock Out</span>
                            <span wire:loading wire:target="clockOut">Clocking out…</span>
                        </button>
                    @else
                        <button
                            wire:click="clockIn"
                            wire:loading.attr="disabled"
                            wire:target="clockIn"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-60 transition-colors"
                        >
                            <span wire:loading.remove wire:target="clockIn"><x-heroicon-o-play-circle class="h-4 w-4" /></span>
                            <span wire:loading wire:target="clockIn">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </span>
                            <span wire:loading.remove wire:target="clockIn">Clock In</span>
                            <span wire:loading wire:target="clockIn">Clocking in…</span>
                        </button>
                    @endif
                </div>
            </div>

        </div>

        {{-- ── Stats Row ───────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-3 gap-4">
            @php
            $statCards = [
                ['label' => 'Today',      'minutes' => $stats['today']],
                ['label' => 'This Week',  'minutes' => $stats['week']],
                ['label' => 'This Month', 'minutes' => $stats['month']],
            ];
            @endphp

            @foreach ($statCards as $card)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ $card['label'] }}</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100 tabular-nums">
                        {{ $card['minutes'] > 0 ? \App\Models\TimeEntry::formatMinutes($card['minutes']) : '—' }}
                    </p>
                </div>
            @endforeach
        </div>

        {{-- ── Team Hours (admin/owner) ────────────────────────────────────── --}}
        @if ($seesTeam)
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 space-y-3">
                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Team Hours</h2>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $this->periodLabel }} — non-admin/hourly workers only</p>
                        </div>
                        @if (count($team) > 0)
                            <a
                                wire:click.prevent="exportTeamHoursCsv"
                                href="#"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition"
                            >
                                <x-heroicon-o-arrow-down-tray class="h-3 w-3" />
                                Export CSV
                            </a>
                        @endif
                    </div>

                    {{-- Period picker --}}
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($periodOptions as $mode => $label)
                            <button
                                wire:click="setPeriodMode('{{ $mode }}')"
                                type="button"
                                class="rounded-full px-3 py-1 text-xs font-medium transition-colors {{ $periodMode === $mode ? 'bg-violet-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }}"
                            >{{ $label }}</button>
                        @endforeach
                    </div>

                    @if ($periodMode === 'custom')
                        <div class="flex items-center gap-2">
                            <input wire:model.live="periodFrom" type="date"
                                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                            <span class="text-xs text-gray-400">to</span>
                            <input wire:model.live="periodTo" type="date"
                                class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-1.5 text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        </div>
                    @endif
                </div>

                @if (count($team) === 0)
                    <div class="px-6 py-8 text-center text-sm text-gray-400">No hours logged by the team for this period.</div>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($team as $member)
                            @php $pct = $team[0]['minutes'] > 0 ? round(($member['minutes'] / $team[0]['minutes']) * 100) : 0; @endphp
                            <div class="px-6 py-3 flex items-center gap-4">
                                <div class="w-6 h-6 rounded-full bg-violet-100 dark:bg-violet-900/40 flex items-center justify-center shrink-0">
                                    <span class="text-xs font-bold text-violet-600 dark:text-violet-400">{{ strtoupper(substr($member['name'], 0, 1)) }}</span>
                                </div>
                                <span class="flex-1 text-sm font-medium text-gray-900 dark:text-gray-100 min-w-0 truncate">{{ $member['name'] }}</span>
                                <span class="text-xs text-gray-400 shrink-0">{{ $member['entries'] }} {{ $member['entries'] === 1 ? 'entry' : 'entries' }}</span>
                                <div class="hidden sm:flex items-center gap-2 w-32">
                                    <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                                        <div class="h-1.5 rounded-full bg-violet-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                                <span class="text-sm font-semibold text-gray-900 dark:text-gray-100 tabular-nums shrink-0">
                                    {{ \App\Models\TimeEntry::formatMinutes($member['minutes']) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- ── History Table ───────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">History</h2>
                <div class="flex items-center gap-3">
                    @if ($entries->total() > 0)
                        <span class="text-xs text-gray-400">{{ number_format($entries->total()) }} entries</span>
                    @endif
                    <a
                        wire:click.prevent="exportCsv"
                        href="#"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition"
                    >
                        <x-heroicon-o-arrow-down-tray class="h-3 w-3" />
                        Export CSV
                    </a>
                </div>
            </div>

            @if ($entries->isEmpty())
                <div class="px-6 py-10 text-center">
                    <x-heroicon-o-clock class="h-10 w-10 text-gray-300 dark:text-gray-600 mx-auto mb-3" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">No time entries yet. Clock in to get started.</p>
                </div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($entries as $e)
                        <div class="px-6 py-3">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    @if ($seesTeam)
                                        {{ $e->user->name ?? '—' }} ·
                                    @endif
                                    {{ $e->clocked_in_at->setTimezone($this->userTimezone())->format('M j, Y') }}
                                </span>
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                    @php $mins = $e->duration_minutes; @endphp
                                    @if ($mins !== null)
                                        {{ \App\Models\TimeEntry::formatMinutes($mins) }}
                                    @else
                                        <span class="font-normal text-gray-400">—</span>
                                    @endif
                                </span>
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                                <span class="tabular-nums text-gray-500 dark:text-gray-400">In {{ $this->formatTimeInUserTz($e->clocked_in_at) }}</span>
                                @if ($e->clocked_out_at)
                                    <span class="tabular-nums text-gray-500 dark:text-gray-400">Out {{ $this->formatTimeInUserTz($e->clocked_out_at) }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Active
                                    </span>
                                @endif
                                @if ($e->notes)
                                    <span class="min-w-0 truncate text-gray-400">{{ $e->notes }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if ($entries->lastPage() > 1)
                    <div class="px-6 py-3 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>Page {{ $entries->currentPage() }} of {{ $entries->lastPage() }}</span>
                        <div class="flex items-center gap-2">
                            <button
                                wire:click="prevPage"
                                @if ($entries->currentPage() === 1) disabled @endif
                                class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-xs hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                            >Previous</button>
                            <button
                                wire:click="nextPage"
                                @if ($entries->currentPage() === $entries->lastPage()) disabled @endif
                                class="rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-xs hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                            >Next</button>
                        </div>
                    </div>
                @endif
            @endif

        </div>

    </div>
</x-filament-panels::page>
