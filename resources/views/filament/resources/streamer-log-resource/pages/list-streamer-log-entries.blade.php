<x-filament-panels::page>
@php
    $isStaff = auth()->user()?->isAdmin() || auth()->user()?->isOwner();
    $queue = $isStaff ? $this->reviewQueue() : ['count' => 0, 'next' => null];
    $tones = ['submissions' => 'violet', 'submitted' => 'blue', 'edit_requested' => 'rose', 'changes_requested' => 'amber', 'approved' => 'green'];
@endphp
<div class="vxi space-y-5" data-vx-page="report-inbox">
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="Filter reports by status">
        @foreach($this->getStats() as $key => $stat)
            <button type="button" wire:click="selectInboxStatus('{{ $key }}')" aria-pressed="{{ $activeTab === $key ? 'true' : 'false' }}" class="vxi-stat vxi-{{ $tones[$key] ?? 'gray' }} {{ $activeTab === $key ? 'is-active' : '' }}">
                <span class="vxi-stat-icon"><x-filament::icon :icon="$stat['icon']" class="h-5 w-5" /></span>
                <span class="min-w-0">
                    <span class="block text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</span>
                    <span class="block text-2xl font-bold leading-tight">{{ $stat['value'] }}</span>
                    <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $stat['sub'] }}</span>
                </span>
            </button>
        @endforeach
    </div>

    @if ($isStaff && $this->reopenRequests()->isNotEmpty())
        <section class="vxi-panel border-amber-200 dark:border-amber-900/60" aria-labelledby="reopen-heading">
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-4"><h3 id="reopen-heading" class="font-bold">Requests to reopen</h3><button type="button" wire:click="selectInboxStatus('edit_requested')" class="text-sm font-semibold text-primary-600 dark:text-primary-400">View all requests →</button></div>
            <div class="grid gap-3 p-4 lg:grid-cols-2">
                @foreach($this->reopenRequests() as $report)
                    <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700" wire:key="reopen-request-{{ $report->id }}">
                        <h4 class="font-semibold">{{ $report->show?->title ?: 'Report #' . $report->id }}</h4><p class="mt-1 text-xs text-gray-500">{{ $report->streamer?->name ?: 'No streamer linked' }} · {{ $report->show?->show_date?->format('M j, Y') ?: 'No show date' }} · Requested {{ $report->revision_requested_at?->diffForHumans() }}</p>
                        <p class="mt-3 whitespace-pre-line text-sm">{{ $report->revision_reason ?: 'The streamer did not include a reason.' }}</p>
                        <div class="mt-4 flex flex-wrap gap-2"><a href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('edit', ['record' => $report]) }}" class="vxi-btn">Review report</a><button type="button" wire:click="reopenRequestedReport({{ $report->id }})" wire:confirm="Reopen this report for the streamer? Existing inventory postings remain in place." wire:loading.attr="disabled" wire:target="reopenRequestedReport" class="vxi-btn bg-amber-500 border-amber-500 text-gray-950">Approve & reopen</button></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="vxi-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 dark:border-gray-700">
            <nav class="vxi-tabs" aria-label="Report views">
                @foreach(['attention' => 'Needs attention', 'all' => 'All reports', 'approved' => 'Approved'] as $key => $label)
                    <button type="button" wire:click="selectInboxStatus('{{ $key }}')" aria-current="{{ $activeTab === $key ? 'page' : 'false' }}" class="{{ $activeTab === $key ? 'is-active' : '' }}">
                        {{ $label }}
                        @if ($key === 'attention' && $isStaff && $queue['count'] > 0)<span class="vxi-count">{{ $queue['count'] }}</span>@endif
                    </button>
                @endforeach
            </nav>
            <label class="flex items-center gap-2 py-2 text-sm text-gray-500 dark:text-gray-400">
                Sort
                <select wire:model.live="tableSort" class="vxi-select">
                    <option value="">Newest first</option>
                    <option value="submitted_at:desc">Recently submitted</option>
                    <option value="submitted_at:asc">Waiting longest</option>
                    <option value="report:desc">Show date, newest</option>
                    <option value="report:asc">Show date, oldest</option>
                </select>
            </label>
        </div>

        @if ($isStaff && $queue['count'] > 0)
            <div class="vxi-ready">
                <span><x-filament::icon icon="heroicon-m-inbox-arrow-down" class="inline h-4 w-4 align-[-2px]" /> <strong>{{ $queue['count'] }} {{ \Illuminate\Support\Str::plural('report', $queue['count']) }}</strong> {{ $queue['count'] === 1 ? 'is' : 'are' }} ready for admin review.</span>
                @if ($queue['next'])<a href="{{ $queue['next'] }}" class="font-semibold">Review next →</a>@endif
            </div>
        @endif

        <div class="vxi-table min-w-0">{{ $this->table }}</div>
    </section>

    <ol class="vxi-flow" aria-label="How a report moves">
        <li><strong>Streamer logs the show</strong><span>Items sold, costs, hours and shipments.</span></li>
        <li><strong>Submits for review</strong><span>The report locks and stock posts.</span></li>
        <li><strong>Admin approves</strong><span>Or requests changes with a note.</span></li>
        <li><strong>Pay is calculated</strong><span>The pay shown here feeds the next pay run.</span></li>
    </ol>
</div>

<style>
    .vxi { --vxi-line: rgb(229 231 235); }
    .dark .vxi { --vxi-line: rgb(55 65 81); }
    .vxi-btn { display:inline-flex; align-items:center; gap:.4rem; min-height:2.5rem; padding:0 .9rem; border:1px solid var(--vxi-line); border-radius:.75rem; font-size:.875rem; font-weight:600; transition:border-color .15s, background-color .15s; }
    .vxi-btn:hover { border-color: var(--primary-400, #7c3aed); }
    .vxi-panel { border:1px solid var(--vxi-line); border-radius:1rem; background:#fff; overflow:hidden; }
    .dark .vxi-panel { background: rgb(17 24 39); }
    .vxi-stat { display:flex; gap:.75rem; align-items:flex-start; text-align:left; padding:1rem; border:1px solid var(--vxi-line); border-radius:1rem; background:#fff; transition:transform .15s ease, border-color .15s, box-shadow .15s; }
    .dark .vxi-stat { background: rgb(17 24 39); }
    .vxi-stat:hover { transform: translateY(-1px); box-shadow: 0 6px 16px -10px rgb(76 29 149 / .35); }
    .vxi-stat.is-active { border-color: var(--vxi-c); box-shadow: 0 0 0 3px color-mix(in srgb, var(--vxi-c) 18%, transparent); }
    .vxi-stat-icon { display:grid; place-items:center; flex:none; width:2.25rem; height:2.25rem; border-radius:.7rem; color: var(--vxi-c); background: color-mix(in srgb, var(--vxi-c) 13%, transparent); }
    .vxi-violet { --vxi-c:#7c3aed; } .vxi-blue { --vxi-c:#2563eb; } .vxi-rose { --vxi-c:#e11d48; } .vxi-amber { --vxi-c:#d97706; } .vxi-green { --vxi-c:#059669; } .vxi-gray { --vxi-c:#6b7280; }
    .dark .vxi-violet { --vxi-c:#a78bfa; } .dark .vxi-blue { --vxi-c:#60a5fa; } .dark .vxi-rose { --vxi-c:#fb7185; } .dark .vxi-amber { --vxi-c:#fbbf24; } .dark .vxi-green { --vxi-c:#34d399; } .dark .vxi-gray { --vxi-c:#9ca3af; }
    .vxi-tabs { display:flex; gap:1.25rem; overflow-x:auto; }
    .vxi-tabs button { position:relative; display:inline-flex; align-items:center; gap:.4rem; padding:.9rem 0; font-size:.875rem; font-weight:600; color: rgb(107 114 128); white-space:nowrap; }
    .vxi-tabs button.is-active { color: var(--primary-600, #7c3aed); }
    .dark .vxi-tabs button.is-active { color: var(--primary-400, #7c3aed); }
    .vxi-tabs button.is-active::after { content:''; position:absolute; left:0; right:0; bottom:-1px; height:2px; border-radius:2px; background: currentColor; }
    .vxi-count { min-width:1.25rem; padding:0 .4rem; border-radius:999px; font-size:.7rem; line-height:1.25rem; text-align:center; color:#fff; background: var(--primary-600, #7c3aed); }
    .vxi-select { min-height:2.25rem; border-radius:.6rem; border-color: var(--vxi-line); font-size:.875rem; padding-top:0; padding-bottom:0; }
    .dark .vxi-select { background: rgb(17 24 39); color:#e5e7eb; }
    .vxi-ready { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.5rem; margin:1rem 1rem 0; padding:.7rem 1rem; border-radius:.75rem; font-size:.875rem; color: rgb(76 29 149); background: rgb(237 233 254); }
    .dark .vxi-ready { color: rgb(221 214 254); background: rgb(76 29 149 / .35); }
    .vxi-ready a:hover { text-decoration: underline; }
    .vxi-table .fi-ta-ctn { border:0; box-shadow:none; border-radius:0; background:transparent; }
    .vxi-table .fi-ta-cell { vertical-align: middle; }
    .vxi-table .fi-ta-row { transition: background-color .15s; }
    .vxi-show { display:flex; gap:.75rem; align-items:center; min-width:14rem; }
    .vxi-thumb { flex:none; width:3rem; height:3rem; border-radius:.65rem; object-fit:cover; }
    .vxi-thumb-blank { display:grid; place-items:center; font-weight:800; color:#fff; background: linear-gradient(135deg, #4c1d95, #7c3aed 60%, #c084fc); }
    .vxi-title { max-width:17rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; }
    .vxi-meta { font-size:.75rem; color: rgb(107 114 128); }
    .vxi-who { display:flex; align-items:center; gap:.35rem; margin-top:.2rem; font-size:.75rem; font-weight:500; }
    .vxi-initials { display:grid; place-items:center; width:1.25rem; height:1.25rem; border-radius:999px; font-size:.55rem; font-weight:700; color: rgb(76 29 149); background: rgb(237 233 254); }
    .dark .vxi-initials { color: rgb(221 214 254); background: rgb(76 29 149 / .5); }
    .vxi-status { min-width:8.5rem; }
    .vxi-pill { display:inline-flex; align-items:center; gap:.35rem; padding:.15rem .6rem; border-radius:999px; font-size:.75rem; font-weight:600; color: var(--vxi-c); background: color-mix(in srgb, var(--vxi-c) 12%, transparent); }
    .vxi-pill::before { content:''; width:.4rem; height:.4rem; border-radius:999px; background: currentColor; }
    .vxi-note { max-width:13rem; margin-top:.3rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.75rem; color: rgb(107 114 128); }
    .vxi-money { display:grid; grid-template-columns: repeat(3, auto); gap:.25rem .9rem; }
    .vxi-money dt { font-size:.7rem; color: rgb(107 114 128); }
    .vxi-money dd { font-size:.875rem; font-weight:600; font-variant-numeric: tabular-nums; }
    .vxi-pay dd { color: rgb(5 150 105); }
    .dark .vxi-pay dd { color: rgb(52 211 153); }
    .vxi-pay.is-negative dd { color: rgb(225 29 72); }
    .dark .vxi-pay.is-negative dd { color: rgb(251 113 133); }
    .vxi-pay[title] { cursor: help; }
    .vxi-when { font-size:.875rem; font-weight:500; white-space:nowrap; }
    .vxi-flow { display:grid; gap:.75rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); counter-reset: vxi; }
    .vxi-flow li { counter-increment: vxi; display:flex; flex-direction:column; gap:.15rem; padding:.85rem 1rem .85rem 3rem; position:relative; border:1px dashed var(--vxi-line); border-radius:.9rem; font-size:.8rem; color: rgb(107 114 128); }
    .vxi-flow li strong { color: rgb(17 24 39); font-size:.85rem; }
    .dark .vxi-flow li strong { color: rgb(243 244 246); }
    .vxi-flow li::before { content: counter(vxi); position:absolute; left:1rem; top:.9rem; display:grid; place-items:center; width:1.4rem; height:1.4rem; border-radius:999px; font-size:.7rem; font-weight:700; color:#fff; background: linear-gradient(135deg, #4c1d95, #7c3aed); }
    @media (prefers-reduced-motion: reduce) { .vxi-stat, .vxi-stat:hover { transition:none; transform:none; } }
</style>
</x-filament-panels::page>
