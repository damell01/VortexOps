<x-filament-panels::page>
    @php
        $trendClass = fn ($v) => match(true) {
            $v === null  => 'text-gray-400',
            $v > 0       => 'text-emerald-600 dark:text-emerald-400',
            $v < 0       => 'text-red-500 dark:text-red-400',
            default      => 'text-gray-400',
        };
        $trendIcon = fn ($v) => match(true) {
            $v === null  => '',
            $v > 0       => '↑',
            $v < 0       => '↓',
            default      => '→',
        };
        $pipelineColors = [
            'draft'    => ['dot' => 'bg-gray-400',    'text' => 'text-gray-600 dark:text-gray-300'],
            'approved' => ['dot' => 'bg-sky-500',     'text' => 'text-sky-600 dark:text-sky-400'],
            'paid'     => ['dot' => 'bg-emerald-500', 'text' => 'text-emerald-600 dark:text-emerald-400'],
        ];
    @endphp

    <div class="space-y-6">

        {{-- Prebuilt report hub: users choose a useful report, not build one. --}}
        <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-950 dark:text-white">Reports & Analytics</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Open a ready-to-use report, filter the data you need, then export it.</p>
                </div>
                <span class="rounded-full bg-primary-50 px-3 py-1 text-[11px] font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">Prebuilt reports</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @php
                    $reportCards = array_filter([
                        ['Show Performance', 'Revenue, net, margin, trends and channel results', '#show-performance', 'heroicon-o-presentation-chart-line'],
                        $this->inventoryReportUrl() ? ['Inventory', 'Value, stock, movement and aging', $this->inventoryReportUrl(), 'heroicon-o-cube'] : null,
                        $this->streamerAnalyticsUrl() ? ['Streamer Analytics', 'Revenue, hours, margin and payouts', $this->streamerAnalyticsUrl(), 'heroicon-o-users'] : null,
                        $this->streamerStatementUrl() ? ['Streamer Statements', 'Show-by-show pay statements', $this->streamerStatementUrl(), 'heroicon-o-document-text'] : null,
                        $this->financeLedgerUrl() ? ['Whatnot Ledger', 'Channel transactions, status and net activity', $this->financeLedgerUrl(), 'heroicon-o-banknotes'] : null,
                        $this->productInsightsUrl() ? ['Product Insights', 'Velocity, value and product performance', $this->productInsightsUrl(), 'heroicon-o-chart-bar-square'] : null,
                    ]);
                @endphp
                @foreach($reportCards as [$label,$description,$url,$icon])
                    <a href="{{ $url }}" class="group rounded-xl border border-gray-200 p-4 transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:hover:border-primary-700">
                        <div class="mb-3 grid h-9 w-9 place-items-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-300">
                            <x-dynamic-component :component="$icon" class="h-5 w-5"/>
                        </div>
                        <div class="text-sm font-bold text-gray-900 dark:text-white">{{ $label }}</div>
                        <div class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ $description }}</div>
                        <div class="mt-3 text-xs font-semibold text-primary-600 dark:text-primary-400">Open report →</div>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Period selector + date range + export --}}
        <div id="show-performance" class="scroll-mt-6 flex flex-wrap items-center gap-2 gap-y-2">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Period:</span>
            @foreach ($this->getPeriodOptions() as $days => $label)
                <button
                    wire:click="setPeriod('{{ $days }}')"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition
                        {{ $period === (string) $days
                            ? 'bg-primary-600 text-white shadow-sm'
                            : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    {{ $label }}
                </button>
            @endforeach

            <span class="text-gray-300 dark:text-gray-600 mx-1">|</span>

            {{-- Custom date range --}}
            <div class="flex items-center gap-1.5">
                <input
                    type="date"
                    wire:model="dateFrom"
                    class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                />
                <span class="text-xs text-gray-400">to</span>
                <input
                    type="date"
                    wire:model="dateTo"
                    class="rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 focus:ring-primary-500 focus:border-primary-500"
                />
                <button
                    wire:click="applyCustomRange"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition {{ $period === 'custom' ? 'bg-primary-600 text-white shadow-sm' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    Apply
                </button>
            </div>

            <span class="flex-1"></span>

            {{-- AI narrative summary --}}
            @if ($this->aiNarrativeEnabled())
                <button
                    type="button"
                    wire:click="generateNarrative"
                    wire:loading.attr="disabled"
                    wire:target="generateNarrative"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition disabled:opacity-60"
                >
                    <x-heroicon-o-sparkles class="h-3.5 w-3.5" wire:loading.remove wire:target="generateNarrative" />
                    <svg wire:loading wire:target="generateNarrative" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="generateNarrative">Summarize this period</span>
                    <span wire:loading wire:target="generateNarrative">Summarizing…</span>
                </button>
            @endif

            {{-- CSV export --}}
            <a
                wire:click.prevent="exportCsv"
                href="#"
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition"