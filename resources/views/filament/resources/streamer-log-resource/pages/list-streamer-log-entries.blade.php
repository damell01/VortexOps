<x-filament-panels::page>
@php
    $isStaff = auth()->user()?->isAdmin() || auth()->user()?->isOwner();
    $queue = $isStaff ? $this->reviewQueue() : ['count' => 0, 'next' => null];
    $tones = ['submissions' => 'violet', 'submitted' => 'blue', 'edit_requested' => 'rose', 'changes_requested' => 'amber', 'approved' => 'green'];
    $policy = $this->reviewPolicy();
    $counts = $this->tabCounts();
    $options = $this->filterOptions();
@endphp
<div class="vxi space-y-4" data-vx-page="report-inbox">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Filter reports by status">
        @foreach($this->getStats() as $key => $stat)
            <button type="button" wire:click="selectInboxStatus('{{ $key }}')" aria-pressed="{{ $activeTab === $key ? 'true' : 'false' }}" title="{{ $stat['sub'] }}" class="vxi-stat vxi-{{ $tones[$key] ?? 'gray' }} {{ $activeTab === $key ? 'is-active' : '' }}">
                <span class="vxi-stat-icon"><x-filament::icon :icon="$stat['icon']" class="h-5 w-5" /></span>
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-gray-600 dark:text-gray-300">{{ $stat['label'] }}</span>
                    <span class="block text-2xl font-bold leading-tight">{{ $stat['value'] }}</span>
                </span>
            </button>
        @endforeach
    </div>

    @if ($isStaff)
        <div class="vxi-policy vxi-{{ $policy['tone'] }}">
            <x-filament::icon :icon="$policy['tone'] === 'green' ? 'heroicon-s-shield-check' : 'heroicon-s-clipboard-document-check'" class="h-5 w-5 shrink-0" />
            <strong>{{ $policy['title'] }}</strong>
            <span class="vxi-policy-sep" aria-hidden="true"></span>
            <span class="min-w-0 flex-1 text-gray-600 dark:text-gray-300">{{ $policy['body'] }}</span>
            @if ($queue['count'] > 0 && $queue['next'])
                <a href="{{ $queue['next'] }}" class="vxi-policy-link">{{ $queue['count'] }} ready · Review next →</a>
            @endif
            @if ($policy['url'])
                <a href="{{ $policy['url'] }}" class="vxi-policy-link"><x-filament::icon icon="heroicon-m-cog-6-tooth" class="h-4 w-4" /> Workflow settings</a>
            @endif
        </div>
    @endif

    @if ($isStaff && $this->reopenRequests()->isNotEmpty())
        <section class="vxi-panel border-rose-200 dark:border-rose-900/60" aria-labelledby="reopen-heading">
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-4"><h3 id="reopen-heading" class="font-bold">Requests to reopen</h3><button type="button" wire:click="selectInboxStatus('edit_requested')" class="text-sm font-semibold text-primary-600 dark:text-primary-400">View all requests →</button></div>
            <div class="grid gap-3 p-4 lg:grid-cols-2">
                @foreach($this->reopenRequests() as $report)
                    <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700" wire:key="reopen-request-{{ $report->id }}">
                        <h4 class="font-semibold">{{ $report->show?->title ?: 'Report #' . $report->id }}</h4><p class="mt-1 text-xs text-gray-500">{{ $report->streamer?->name ?: 'No streamer linked' }} · {{ $report->show?->show_date?->format('M j, Y') ?: 'No show date' }} · Requested {{ $report->revision_requested_at?->diffForHumans() }}</p>
                        <p class="mt-3 whitespace-pre-line text-sm">{{ $report->revision_reason ?: 'The streamer did not include a reason.' }}</p>
                        <div class="mt-4 flex flex-wrap gap-2"><a href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('edit', ['record' => $report]) }}" class="vxi-btn">Review report</a><button type="button" wire:click="reopenRequestedReport({{ $report->id }})" wire:confirm="Reopen this report for the streamer? Existing inventory postings remain in place." wire:loading.attr="disabled" wire:target="reopenRequestedReport" class="vxi-btn border-amber-500 bg-amber-500 text-gray-950">Approve & reopen</button></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="vxi-panel" x-data="{ more: false, selecting: false }" :class="selecting && 'is-selecting'">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 dark:border-gray-700">
            <nav class="vxi-tabs" aria-label="Report views">
                @foreach(['all' => 'All reports', 'attention' => 'Needs attention', 'approved' => 'Approved'] as $key => $label)
                    <button type="button" wire:click="selectInboxStatus('{{ $key }}')" aria-current="{{ $activeTab === $key ? 'page' : 'false' }}" class="{{ $activeTab === $key ? 'is-active' : '' }}">{{ $label }} ({{ number_format($counts[$key] ?? 0) }})</button>
                @endforeach
            </nav>
            <div class="flex flex-wrap items-center gap-2 py-2">
                <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">Sort
                    <select wire:model.live="inboxSort" class="vxi-select">
                        <option value="">Newest first</option>
                        <option value="submitted_desc">Recently submitted</option>
                        <option value="waiting_longest">Waiting longest</option>
                        <option value="show_desc">Show date, newest</option>
                        <option value="show_asc">Show date, oldest</option>
                    </select>
                </label>
                <select wire:model.live="tableRecordsPerPage" class="vxi-select" aria-label="Reports per page">
                    @foreach ([6, 12, 24] as $n)<option value="{{ $n }}">{{ $n }} per page</option>@endforeach
                </select>
                @if ($isStaff)
                    <button type="button" class="vxi-btn" @click="selecting = !selecting" :aria-pressed="selecting" x-text="selecting ? 'Done selecting' : 'Select'" title="Pick several reports to approve or send back together">Select</button>
                @endif
            </div>
        </div>

        <div class="vxi-filterbar">
            <label class="vxi-search">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-5 w-5 text-gray-400" />
                <span class="sr-only">Search</span>
                <input type="search" wire:model.live.debounce.400ms="tableSearch" placeholder="Search show or streamer…">
            </label>
            @if ($options['streamers'])
                <label class="vxi-field"><span>Streamer</span>
                    <select wire:model.live="tableFilters.streamer_id.value" class="vxi-select"><option value="">All streamers</option>@foreach ($options['streamers'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                </label>
            @endif
            <label class="vxi-field"><span>Channel</span>
                <select wire:model.live="tableFilters.channel.value" class="vxi-select"><option value="">All channels</option>@foreach ($options['channels'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
            </label>
            <label class="vxi-field"><span>Date range</span>
                <select wire:model.live="tableFilters.date_range.value" class="vxi-select"><option value="">All dates</option><option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="this_month">This month</option><option value="last_month">Last month</option></select>
            </label>
            <button type="button" class="vxi-btn self-end" @click="more = !more" :aria-expanded="more"><x-filament::icon icon="heroicon-m-funnel" class="h-4 w-4" /> Filters</button>
        </div>
        <div class="vxi-filterbar pt-0" x-show="more" x-cloak x-transition.opacity>
            <label class="vxi-field"><span>Status</span>
                <select wire:model.live="tableFilters.status.value" class="vxi-select"><option value="">Any status</option>@foreach (\App\Models\StreamerLogEntry::statusLabels() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
            </label>
            <label class="vxi-field"><span>Show date from</span><input type="date" wire:model.live="tableFilters.show_date.from" class="vxi-select"></label>
            <label class="vxi-field"><span>Show date until</span><input type="date" wire:model.live="tableFilters.show_date.until" class="vxi-select"></label>
            <button type="button" class="vxi-btn self-end" wire:click="resetTableFiltersForm">Clear filters</button>
        </div>

        <div class="vxi-grid min-w-0">{{ $this->table }}</div>
    </section>

    <details class="vxi-howto">
        <summary>
            <x-filament::icon icon="heroicon-o-book-open" class="h-5 w-5 shrink-0 text-gray-500" />
            <span><strong>How reporting works</strong> <span class="text-gray-500">· Based on current workflow settings</span><br>
            <span class="text-xs text-gray-500">Log show → Submit &amp; lock report → {{ $policy['tone'] === 'green' ? 'Auto-approve or flag issue' : 'Admin approves or requests changes' }} → Calculate pay</span></span>
            <x-filament::icon icon="heroicon-m-chevron-down" class="vxi-howto-chev ml-auto h-5 w-5 text-gray-500" />
        </summary>
        <ol class="vxi-flow">
            <li><strong>Streamer logs the show</strong><span>Items sold, costs, hours and shipments.</span></li>
            <li><strong>Submits &amp; locks the report</strong><span>Stock posts when the report is filed.</span></li>
            <li><strong>{{ $policy['tone'] === 'green' ? 'Auto-approve or flag' : 'Admin approves' }}</strong><span>{{ $policy['body'] }}</span></li>
            <li><strong>Pay is calculated</strong><span>The pay on each card is what the next pay run will use. Missing hours, earnings or costs are flagged on the card.</span></li>
        </ol>
    </details>
</div>

<style>
    .vxi { --vxi-line: rgb(229 231 235); --vxi-card: #fff; }
    .dark .vxi { --vxi-line: rgb(55 65 81); --vxi-card: rgb(17 24 39); }
    [x-cloak] { display: none !important; }
    .vxi-btn { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; min-height:2.5rem; padding:0 .9rem; border:1px solid var(--vxi-line); border-radius:.75rem; font-size:.875rem; font-weight:600; background: var(--vxi-card); transition:border-color .15s; }
    .vxi-btn:hover { border-color: var(--primary-400, #a78bfa); }
    .vxi-panel { border:1px solid var(--vxi-line); border-radius:1rem; background: var(--vxi-card); overflow:hidden; }
    .vxi-stat { display:flex; gap:.85rem; align-items:center; text-align:left; padding:1rem 1.1rem; border:1px solid var(--vxi-line); border-radius:1rem; background: var(--vxi-card); transition:transform .15s ease, border-color .15s, box-shadow .15s; }
    .vxi-stat:hover { transform: translateY(-1px); box-shadow: 0 6px 16px -10px rgb(76 29 149 / .35); }
    .vxi-stat.is-active { border-color: var(--vxi-c); background: color-mix(in srgb, var(--vxi-c) 5%, var(--vxi-card)); box-shadow: 0 0 0 1px var(--vxi-c); }
    .vxi-stat-icon { display:grid; place-items:center; flex:none; width:2.6rem; height:2.6rem; border-radius:.8rem; color: var(--vxi-c); background: color-mix(in srgb, var(--vxi-c) 13%, transparent); }
    .vxi-violet { --vxi-c:#7c3aed; } .vxi-blue { --vxi-c:#2563eb; } .vxi-rose { --vxi-c:#e11d48; } .vxi-amber { --vxi-c:#d97706; } .vxi-green { --vxi-c:#059669; } .vxi-gray { --vxi-c:#6b7280; }
    .dark .vxi-violet { --vxi-c:#a78bfa; } .dark .vxi-blue { --vxi-c:#60a5fa; } .dark .vxi-rose { --vxi-c:#fb7185; } .dark .vxi-amber { --vxi-c:#fbbf24; } .dark .vxi-green { --vxi-c:#34d399; } .dark .vxi-gray { --vxi-c:#9ca3af; }
    .vxi-policy { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem .75rem; padding:.8rem 1.1rem; border:1px solid color-mix(in srgb, var(--vxi-c) 25%, var(--vxi-line)); border-radius:1rem; font-size:.875rem; background: color-mix(in srgb, var(--vxi-c) 6%, var(--vxi-card)); }
    .vxi-policy > svg, .vxi-policy > strong { color: var(--vxi-c); }
    .vxi-policy-sep { width:1px; height:1rem; background: var(--vxi-line); }
    .vxi-policy-link { display:inline-flex; align-items:center; gap:.35rem; font-weight:600; color: var(--primary-600, #7c3aed); }
    .dark .vxi-policy-link { color: var(--primary-400, #a78bfa); }
    .vxi-policy-link:hover { text-decoration: underline; }
    .vxi-tabs { display:flex; gap:1.75rem; overflow-x:auto; }
    .vxi-tabs button { position:relative; padding:1rem 0; font-size:.9rem; font-weight:600; color: rgb(75 85 99); white-space:nowrap; }
    .dark .vxi-tabs button { color: rgb(156 163 175); }
    .vxi-tabs button.is-active { color: var(--primary-600, #7c3aed); }
    .dark .vxi-tabs button.is-active { color: var(--primary-400, #a78bfa); }
    .vxi-tabs button.is-active::after { content:''; position:absolute; left:-.5rem; right:-.5rem; bottom:-1px; height:3px; border-radius:3px 3px 0 0; background: currentColor; }
    .vxi-select { min-height:2.5rem; border-radius:.65rem; border:1px solid var(--vxi-line); background-color: var(--vxi-card); font-size:.875rem; padding:.35rem 2rem .35rem .75rem; }
    .dark .vxi-select { color:#e5e7eb; }
    .vxi-filterbar { display:grid; gap:.75rem; padding:1rem; grid-template-columns: 1fr; align-items:end; }
    @media (min-width: 768px) { .vxi-filterbar { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1280px) { .vxi-filterbar { grid-template-columns: minmax(14rem, 2fr) repeat(3, minmax(0, 1.4fr)) auto; } }
    .vxi-field { display:flex; flex-direction:column; gap:.2rem; font-size:.75rem; color: rgb(107 114 128); }
    .vxi-field .vxi-select { width:100%; }
    .vxi-search { display:flex; align-items:center; gap:.5rem; min-height:2.5rem; padding:0 .75rem; border:1px solid var(--vxi-line); border-radius:.65rem; align-self:end; }
    .vxi-search input { flex:1; min-width:0; border:0; background:transparent; font-size:.875rem; padding:.5rem 0; box-shadow:none; }
    .vxi-search input:focus { outline:none; box-shadow:none; }
    .vxi-search:focus-within { border-color: var(--primary-500, #8b5cf6); }

    /* Filament's table chrome, folded into the panel: its own search and filters are replaced by the bar above. */
    .vxi-grid .fi-ta-ctn { border:0; box-shadow:none; border-radius:0; background:transparent; }
    .vxi-grid .fi-ta-header-toolbar .fi-ta-search-field, .vxi-grid .fi-ta-filters-above-content-ctn, .vxi-grid .fi-ta-filter-indicators { display:none; }
    .vxi-grid .fi-ta-content-grid { padding: .25rem 1rem 1rem; gap: 1rem; }
    .vxi-grid .fi-ta-record { position:relative; border:1px solid var(--vxi-line); border-radius:1rem; background: var(--vxi-card); box-shadow: 0 1px 2px rgb(0 0 0 / .04); transition: box-shadow .15s, transform .15s; }
    .vxi-grid .fi-ta-record:hover { box-shadow: 0 10px 24px -14px rgb(76 29 149 / .45); transform: translateY(-1px); }
    .vxi-grid .fi-ta-record { padding:0 !important; display:block; }
    .vxi-grid .fi-ta-record .fi-ta-record-content-ctn { padding:0; display:block; }
    .vxi-grid .fi-ta-record .fi-ta-record-content { display:block; padding:0; }
    /* The row's actions sit in the card itself: ⋮ top right, buttons bottom right. */
    .vxi-grid .fi-ta-record .fi-ta-actions { display:contents; }
    .vxi-grid .fi-ta-record .fi-ta-actions > .fi-dropdown { position:absolute; top:.8rem; right:.6rem; }
    .vxi-grid .fi-ta-record .fi-ta-actions > .vxi-primary { position:absolute; right:1rem; bottom:.85rem; }
    .vxi-grid .fi-ta-record .fi-ta-actions > .vxi-approve { position:absolute; right:7.5rem; bottom:.85rem; }
    .vxi-grid .fi-ta-record .fi-ta-record-checkbox { position:absolute; left:1.1rem; bottom:1.15rem; z-index:1; }
    .vxi-grid .fi-ta-record .fi-growable, .vxi-grid .fi-ta-record .fi-ta-stack, .vxi-grid .fi-ta-record .fi-ta-col { width:100%; }
    .vxi-grid .fi-ta-record .fi-ta-col > * { width:100%; }
    /* Bulk selection stays out of the way until Select is pressed. */
    .vxi-panel:not(.is-selecting) .vxi-grid .fi-ta-record-checkbox,
    .vxi-panel:not(.is-selecting) .vxi-grid .fi-ta-header-toolbar,
    .vxi-panel:not(.is-selecting) .vxi-grid .fi-ta-header-ctn,
    .vxi-panel:not(.is-selecting) .vxi-grid .fi-ta-selection-indicator,
    .vxi-panel:not(.is-selecting) .vxi-grid .fi-ta-content-header { display:none; }
    .vxi-panel.is-selecting .vxi-grid .vxi-card-foot { left:2.6rem; }
    .vxi-grid .fi-ta-record .vxi-primary, .vxi-grid .fi-ta-record .vxi-approve { min-height:2.25rem; padding:0 1rem; border-radius:.65rem; }
    .vxi-grid .fi-ta-record .vxi-primary { border:1px solid color-mix(in srgb, var(--primary-500, #8b5cf6) 55%, transparent); background: var(--vxi-card); }
    .vxi-grid .fi-ta-record .vxi-primary:hover { background: color-mix(in srgb, var(--primary-500, #8b5cf6) 8%, var(--vxi-card)); }

    .vxi-card { display:flex; flex-direction:column; gap:.6rem; padding:1.1rem 1.1rem 4rem; min-width:0; }
    .vxi-card-top { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding-right:2rem; }
    .vxi-chan { display:inline-flex; align-items:center; gap:.4rem; padding:.15rem .55rem; border:1px solid var(--vxi-line); border-radius:.45rem; font-size:.75rem; font-weight:500; max-width:60%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .vxi-chan-dot { width:.55rem; height:.55rem; border-radius:.15rem; background: linear-gradient(135deg, #4c1d95, #a855f7); flex:none; }
    .vxi-chan--none { color: rgb(156 163 175); }
    .vxi-pill { display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .65rem; border-radius:999px; font-size:.75rem; font-weight:600; color: var(--vxi-c); background: color-mix(in srgb, var(--vxi-c) 12%, transparent); white-space:nowrap; }
    .vxi-pill::before { content:''; width:.45rem; height:.45rem; border-radius:999px; border:1.5px solid currentColor; }
    .vxi-card-title { font-size:1.05rem; font-weight:700; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .vxi-meta { font-size:.8rem; color: rgb(107 114 128); }
    .vxi-who { display:flex; align-items:center; gap:.6rem; font-size:.875rem; }
    .vxi-initials { display:grid; place-items:center; width:2rem; height:2rem; border-radius:999px; font-size:.7rem; font-weight:700; color: rgb(76 29 149); background: rgb(237 233 254); flex:none; }
    .dark .vxi-initials { color: rgb(221 214 254); background: rgb(76 29 149 / .5); }
    .vxi-strip { display:flex; align-items:flex-start; gap:.45rem; padding:.4rem .6rem; border-radius:.5rem; font-size:.75rem; line-height:1.35; color: var(--vxi-c); background: color-mix(in srgb, var(--vxi-c) 10%, transparent); min-width:0; }
    .vxi-strip .truncate { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .vxi-amber.vxi-strip { color: rgb(180 83 9); }
    .dark .vxi-amber.vxi-strip { color: rgb(252 211 77); }
    .vxi-figs { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top:auto; padding-top:.75rem; border-top:1px solid var(--vxi-line); }
    .vxi-figs > div { padding: 0 .6rem; border-left:1px solid var(--vxi-line); min-width:0; }
    .vxi-figs > div:first-child { padding-left:0; border-left:0; }
    .vxi-figs dt { font-size:.72rem; color: rgb(107 114 128); }
    .vxi-figs dd { font-size:1rem; font-weight:700; font-variant-numeric: tabular-nums; overflow:hidden; text-overflow:ellipsis; }
    .vxi-pay dd { color: #7c3aed; }
    .dark .vxi-pay dd { color: #c4b5fd; }
    .vxi-pay.is-negative dd { color: rgb(225 29 72); }
    .dark .vxi-pay.is-negative dd { color: rgb(251 113 133); }
    .vxi-pay[title] { cursor: help; }
    .vxi-card-foot { position:absolute; left:1.1rem; bottom:1.45rem; right:16rem; font-size:.75rem; color: rgb(107 114 128); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .vxi-primary { border-radius:.65rem !important; }

    .vxi-howto { border:1px solid var(--vxi-line); border-radius:1rem; background: var(--vxi-card); }
    .vxi-howto summary { display:flex; align-items:center; gap:.75rem; padding:.85rem 1.1rem; font-size:.875rem; cursor:pointer; list-style:none; }
    .vxi-howto summary::-webkit-details-marker { display:none; }
    .vxi-howto[open] .vxi-howto-chev { transform: rotate(180deg); }
    .vxi-flow { display:grid; gap:.75rem; padding:0 1.1rem 1.1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); counter-reset: vxi; }
    .vxi-flow li { counter-increment: vxi; display:flex; flex-direction:column; gap:.15rem; padding:.85rem 1rem .85rem 3rem; position:relative; border:1px dashed var(--vxi-line); border-radius:.9rem; font-size:.8rem; color: rgb(107 114 128); }
    .vxi-flow li strong { color: rgb(17 24 39); font-size:.85rem; }
    .dark .vxi-flow li strong { color: rgb(243 244 246); }
    .vxi-flow li::before { content: counter(vxi); position:absolute; left:1rem; top:.9rem; display:grid; place-items:center; width:1.4rem; height:1.4rem; border-radius:999px; font-size:.7rem; font-weight:700; color:#fff; background: linear-gradient(135deg, #4c1d95, #7c3aed); }
    @media (max-width: 640px) {
        .vxi-policy > span.flex-1 { flex-basis: 100%; order: 3; }
        .vxi-policy-sep { display:none; }
        .vxi-policy-link { order: 4; }
        .vxi-card { padding-bottom: 3.75rem; }
        .vxi-card-foot { position: static; white-space: normal; }
        .vxi-figs > div { padding: 0 .4rem; }
        .vxi-figs dd { font-size: .85rem; overflow: visible; text-overflow: clip; white-space: nowrap; }
        .vxi-tabs { gap: 1.1rem; }
    }
    @media (prefers-reduced-motion: reduce) { .vxi-stat, .vxi-stat:hover, .vxi-grid .fi-ta-record, .vxi-grid .fi-ta-record:hover { transition:none; transform:none; } }
</style>
</x-filament-panels::page>
