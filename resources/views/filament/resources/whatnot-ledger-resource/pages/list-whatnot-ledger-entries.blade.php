<x-filament-panels::page>
@php
    $stats = $this->stats;
    $entries = $this->entries;
    $total = $stats['count'];
    $selected = $this->selected;
    $chips = $this->channelChips;
    $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
    $firstRow = $total ? ($this->pageNo - 1) * $perPage + 1 : 0;
    $lastRow = min($total, $this->pageNo * $perPage);
    $money = fn ($v) => ($v < 0 ? '-' : '').'$'.number_format(abs((float) $v), 2);
    $signed = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '-' : '')).'$'.number_format(abs((float) $v), 2);
    $canDelete = $this->canDelete();
    $sortIcon = fn ($col) => $sort === $col ? ($dir === 'asc' ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down') : 'heroicon-m-chevron-up-down';
    // Numbered pager: up to five pages around the current one.
    $start = max(1, min($this->pageNo - 2, $lastPage - 4));
    $pages = range($start, min($lastPage, $start + 4));
@endphp

<style>
    .vxled { grid-template-columns: minmax(0, 1fr); }
    /* The phone select picker (mobile-controls.js) sits inside our control; let the control be the box. */
    @media (max-width: 767px) {
        .vxled .vxr-control { height: auto; min-height: 48px; }
        .vxled .vxr-control .vx-picker-trigger { border: 0; background: transparent; min-height: 44px; padding: 0; font-size: 15px; flex: 1 1 auto; min-width: 0; }
    }
    .vxled-chips { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
    .vxled-chip { flex: none; display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px; border-radius: 10px; border: 1px solid var(--vxw-border); background: var(--vxw-surface); color: var(--vxw-text); font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .vxled-chip:hover { border-color: var(--vxw-accent-line); }
    .vxled-chip.is-active { background: var(--vxw-accent); border-color: var(--vxw-accent); color: #fff; }
    .vxled-chip .vxr-count { background: var(--vxw-warn-soft); color: var(--vxw-warn); }
    .vxled-toolbar > .vxr-control { flex: 1 1 200px; }
    .vxled-toolbar > .vxr-control select, .vxled-toolbar > .vxr-control input[type=date] { width: 100%; }
    .vxled-toolbar > .vxr-search { flex: 2 1 260px; }
    .vxled-amt { font-weight: 650; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .vxled-details { max-width: 320px; overflow-wrap: anywhere; }
    .vxled-table .vxled-date { white-space: nowrap; }
    .vxled-table .vxled-details { min-width: 160px; }
    .vxled-table .vxled-check { width: 1%; padding-right: 0; }
    .vxled-pages { display: inline-flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .vxled-foot-tools { display: inline-flex; gap: 10px; align-items: center; flex-wrap: wrap; min-width: 0; max-width: 100%; }
    .vxled-pages button { min-width: 34px; height: 34px; padding: 0 8px; border-radius: 8px; border: 1px solid var(--vxw-border); background: var(--vxw-surface); color: var(--vxw-text); font-size: 13.5px; font-weight: 600; cursor: pointer; display: inline-grid; place-items: center; }
    .vxled-pages button.is-active { background: var(--vxw-accent); border-color: var(--vxw-accent); color: #fff; }
    .vxled-pages button[disabled] { opacity: .45; cursor: default; }
    .vxled-pages svg { width: 16px; height: 16px; }
    .vxled-panel { position: sticky; top: 84px; }
    .vxled-panel .vxr-drawer-head { padding: 18px 20px 12px; border-bottom: 0; }
    .vxled-panel-amt { font-size: 30px; font-weight: 750; letter-spacing: -.02em; font-variant-numeric: tabular-nums; }
    .vxled-panel .vxr-dl dd { text-align: right; overflow-wrap: anywhere; min-width: 0; }
    .vxled-panel .vxr-dl dd.vxled-left { text-align: left; }
    .vxled-copy { display: inline-flex; align-items: center; gap: 10px; }
    .vxled-backdrop { display: none; }
    /* Under 1024px the details panel becomes a drawer over the page. */
    @media (max-width: 1023px) {
        .vxled-backdrop { display: block; }
        .vxled-panel { position: fixed; z-index: 1000; top: 0; right: 0; bottom: 0; width: min(440px, 100vw); border-radius: 0; overflow-y: auto; box-shadow: var(--vxw-shadow-pop); }
    }
</style>

<div class="vxr vxled">
    <div class="vxr-head">
        <div>
            <h1 class="vxr-title">Whatnot Ledger</h1>
            <p class="vxr-subtitle">Reconcile credits, debits and transaction status by channel.</p>
        </div>
        <div class="vxr-actions">
            @if ($canDelete && count($checked))
                <button type="button" class="vxr-btn" style="color:var(--vxw-bad)" wire:click="deleteChecked"
                    wire:confirm="Delete {{ count($checked) }} ledger {{ Str::plural('entry', count($checked)) }}? This cannot be undone.">
                    <x-filament::icon icon="heroicon-o-trash" /> Delete selected ({{ count($checked) }})
                </button>
            @endif
            <button type="button" class="vxr-btn vxr-btn--outline" wire:click="exportCsv" title="Download every row the filters select (up to {{ number_format($this::CSV_ROW_LIMIT) }})">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" /> Export CSV
            </button>
        </div>
    </div>

    <div class="vxled-chips" role="tablist" aria-label="Channel">
        @foreach ($chips as $chip)
            <button type="button" role="tab" wire:key="chip-{{ $chip['key'] }}"
                class="vxled-chip {{ $channel === $chip['key'] ? 'is-active' : '' }}"
                aria-selected="{{ $channel === $chip['key'] ? 'true' : 'false' }}"
                wire:click="setChannel('{{ $chip['key'] }}')">
                {{ $chip['label'] }}
                @if ($chip['count'])<span class="vxr-count">{{ $chip['count'] }}</span>@endif
            </button>
        @endforeach
    </div>

    <div class="vxr-toolbar vxr-toolbar--card vxled-toolbar">
        <label class="vxr-control">
            <x-filament::icon icon="heroicon-o-calendar-days" />
            <select wire:model.live="preset" aria-label="Date range">
                @foreach ($this::PRESETS as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if ($preset === 'custom')
            <label class="vxr-control" title="From">
                <span class="vxr-val-muted" style="font-size:13px">From</span>
                <input type="date" wire:model.live="from" aria-label="From date">
            </label>
            <label class="vxr-control" title="Until">
                <span class="vxr-val-muted" style="font-size:13px">To</span>
                <input type="date" wire:model.live="until" aria-label="Until date">
            </label>
        @endif
        <label class="vxr-control">
            <x-filament::icon icon="heroicon-o-list-bullet" />
            <select wire:model.live="type" aria-label="Transaction type">
                <option value="">All transaction types</option>
                @foreach ($this->typeOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </label>
        <label class="vxr-control">
            <x-filament::icon icon="heroicon-o-check-circle" />
            <select wire:model.live="status" aria-label="Status">
                <option value="">All statuses</option>
                @foreach ($this->statusOptions as $opt)
                    <option value="{{ $opt }}">{{ $opt }}</option>
                @endforeach
            </select>
        </label>
        <label class="vxr-control vxr-search">
            <x-filament::icon icon="heroicon-o-magnifying-glass" />
            <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search order or details…" aria-label="Search order or details">
        </label>
    </div>

    <div class="vxr-stats">
        <x-vxr.stat label="Credits" :value="$money($stats['credits'])" icon="heroicon-o-arrow-up" tone="ok" />
        <x-vxr.stat label="Debits" :value="$money($stats['debits'])" icon="heroicon-o-arrow-down" tone="bad" />
        <x-vxr.stat label="Net Activity" :value="$money($stats['net'])" icon="heroicon-o-equals" tone="accent"
            :value-class="$stats['net'] < 0 ? 'vxr-val-bad' : ''" />
        <x-vxr.stat label="Processing" :value="number_format($stats['processing']).' '.Str::plural('transaction', $stats['processing'])"
            icon="heroicon-o-clock" tone="warn" note="Status processing or pending" />
    </div>

    <div class="vxr-grid {{ $selected ? 'vxr-grid--main' : '' }}">
        <div class="vxr-card">
            <div class="vxr-table-wrap">
                <table class="vxr-table vxled-table">
                    <thead>
                        <tr>
                            @if ($canDelete)
                                <th class="vxled-check"><span class="sr-only">Select</span></th>
                            @endif
                            <th><button type="button" wire:click="sortBy('created_date')">Date <x-filament::icon :icon="$sortIcon('created_date')" class="h-4 w-4" /></button></th>
                            <th class="num"><button type="button" wire:click="sortBy('amount')">Amount <x-filament::icon :icon="$sortIcon('amount')" class="h-4 w-4" /></button></th>
                            <th><button type="button" wire:click="sortBy('transaction_type')">Type <x-filament::icon :icon="$sortIcon('transaction_type')" class="h-4 w-4" /></button></th>
                            <th><button type="button" wire:click="sortBy('status')">Status <x-filament::icon :icon="$sortIcon('status')" class="h-4 w-4" /></button></th>
                            <th><button type="button" wire:click="sortBy('whatnot_order_id')">Order ID <x-filament::icon :icon="$sortIcon('whatnot_order_id')" class="h-4 w-4" /></button></th>
                            <th>Details</th>
                            <th><button type="button" wire:click="sortBy('channel')">Channel <x-filament::icon :icon="$sortIcon('channel')" class="h-4 w-4" /></button></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            @php $amt = (float) $entry->amount; @endphp
                            <tr wire:key="led-{{ $entry->id }}" class="is-click {{ $selectedId === $entry->id ? 'is-selected' : '' }}"
                                wire:click="select({{ $entry->id }})">
                                @if ($canDelete)
                                    <td class="vxled-check" x-on:click.stop>
                                        <input type="checkbox" value="{{ $entry->id }}" wire:model.live="checked" aria-label="Select entry {{ $entry->id }}">
                                    </td>
                                @endif
                                <td class="vxled-date">
                                    @if ($entry->created_date)
                                        {{ $entry->created_date->format('M j, Y') }}
                                        <span class="sub">{{ $entry->created_date->format('g:i A') }}</span>
                                    @else
                                        <span class="vxr-val-muted">—</span>
                                    @endif
                                </td>
                                <td class="num">
                                    @if ($entry->amount === null)
                                        <span class="vxr-val-muted">—</span>
                                    @else
                                        <span class="vxled-amt {{ $amt < 0 ? 'vxr-val-bad' : 'vxr-val-ok' }}">{{ $signed($amt) }}</span>
                                    @endif
                                </td>
                                <td>{{ $entry->transaction_type ?: '—' }}</td>
                                <td>
                                    @if ($entry->status)
                                        <span class="vxr-pill {{ ($t = $this::statusTone($entry->status)) ? 'vxr-pill--'.$t : '' }}">{{ $entry->status }}</span>
                                    @else
                                        <span class="vxr-val-muted">—</span>
                                    @endif
                                </td>
                                <td style="font-variant-numeric:tabular-nums">{{ $entry->whatnot_order_id ?: '—' }}</td>
                                <td><div class="vxled-details" title="{{ $entry->message }}">{{ Str::limit((string) $entry->message, 120) ?: '—' }}</div></td>
                                <td style="white-space:nowrap">{{ $entry->channel?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canDelete ? 8 : 7 }}">
                                    <div class="vxr-empty">
                                        <b>No Whatnot ledger entries</b>
                                        @if ($preset !== 'all' || $type !== '' || $status !== '' || trim($search) !== '')
                                            Nothing matches these filters.
                                            <button type="button" class="vxr-link" wire:click="clearFilters">Clear filters</button>
                                        @else
                                            Entries arrive via the nightly ledger import from Whatnot.
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="vxr-card-foot">
                <span>
                    @if ($total)
                        Showing {{ number_format($firstRow) }}–{{ number_format($lastRow) }} of {{ number_format($total) }} {{ Str::plural('transaction', $total) }}
                    @else
                        No transactions
                    @endif
                </span>
                <span class="vxled-foot-tools">
                    <select class="vxr-select" style="height:34px" wire:model.live="perPage" aria-label="Rows per page">
                        @foreach ([10, 25, 50] as $n)
                            <option value="{{ $n }}">{{ $n }} per page</option>
                        @endforeach
                        @if (! in_array($perPage, [10, 25, 50], true))<option value="{{ $perPage }}">{{ $perPage }} per page</option>@endif
                    </select>
                    @if ($lastPage > 1)
                        <nav class="vxled-pages" aria-label="Pages">
                            <button type="button" wire:click="goToPage({{ $this->pageNo - 1 }})" @disabled($this->pageNo <= 1) aria-label="Previous page"><x-filament::icon icon="heroicon-m-chevron-left" /></button>
                            @foreach ($pages as $p)
                                <button type="button" wire:click="goToPage({{ $p }})" class="{{ $p === $this->pageNo ? 'is-active' : '' }}" @if($p === $this->pageNo) aria-current="page" @endif>{{ $p }}</button>
                            @endforeach
                            <button type="button" wire:click="goToPage({{ $this->pageNo + 1 }})" @disabled($this->pageNo >= $lastPage) aria-label="Next page"><x-filament::icon icon="heroicon-m-chevron-right" /></button>
                        </nav>
                    @endif
                </span>
            </div>
        </div>

        @if ($selected)
            @php
                $selAmt = (float) $selected->amount;
                $orderUrl = $this::orderUrl($selected);
                $tone = $this::statusTone($selected->status);
            @endphp
            <div class="vxr-drawer-backdrop vxled-backdrop" wire:click="select(null)"></div>
            <aside class="vxr-card vxled-panel" aria-label="Transaction details" wire:key="panel-{{ $selected->id }}"
                x-data="{ copied: false, copy(v) { navigator.clipboard?.writeText(v); this.copied = true; setTimeout(() => this.copied = false, 1500) } }"
                x-on:keydown.escape.window="$wire.select(null)">
                <div class="vxr-drawer-head">
                    <h2 class="vxr-drawer-title">Transaction Details</h2>
                    <button type="button" class="vxr-x" wire:click="select(null)" aria-label="Close details"><x-filament::icon icon="heroicon-m-x-mark" /></button>
                </div>
                <div class="vxr-card-body" style="padding-top:0;display:grid;gap:14px">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                        <span class="vxled-panel-amt {{ $selected->amount === null ? 'vxr-val-muted' : ($selAmt < 0 ? 'vxr-val-bad' : 'vxr-val-ok') }}">
                            {{ $selected->amount === null ? '—' : $signed($selAmt) }}
                        </span>
                        @if ($selected->status)
                            <span class="vxr-pill {{ $tone ? 'vxr-pill--'.$tone : '' }}">{{ $selected->status }}</span>
                        @endif
                    </div>
                    <dl class="vxr-dl">
                        <div><dt>Created</dt><dd>{{ $selected->created_date?->format('M j, Y \a\t g:i A') ?? '—' }}</dd></div>
                        <div><dt>Completed</dt><dd>{{ $selected->completed_date?->format('M j, Y \a\t g:i A') ?? '—' }}</dd></div>
                        <div>
                            <dt>Order ID</dt>
                            <dd class="vxled-copy">
                                {{ $selected->whatnot_order_id ?: '—' }}
                                @if ($selected->whatnot_order_id)
                                    <button type="button" class="vxr-btn vxr-btn--outline vxr-btn--sm" x-on:click="copy(@js($selected->whatnot_order_id))">
                                        <x-filament::icon icon="heroicon-o-document-duplicate" /> <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                                    </button>
                                @endif
                            </dd>
                        </div>
                        <div><dt>Channel</dt><dd>{{ $selected->channel?->name ?? 'Unassigned' }}</dd></div>
                        <div><dt>Type</dt><dd>{{ $selected->transaction_type ?: '—' }}</dd></div>
                        @if ($selected->listing_id)
                            <div><dt>Listing ID</dt><dd>{{ $selected->listing_id }}</dd></div>
                        @endif
                        <div style="display:grid;gap:4px"><dt>Details</dt><dd class="vxled-left" style="font-weight:500">{{ $selected->message ?: '—' }}</dd></div>
                    </dl>
                </div>
                @if ($orderUrl || $selected->whatnot_order_id)
                    <div class="vxr-drawer-foot" style="justify-content:stretch">
                        @if ($orderUrl)
                            <a href="{{ $orderUrl }}" target="_blank" rel="noopener" class="vxr-btn vxr-btn--outline" style="flex:1">
                                <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" /> View Order
                            </a>
                        @endif
                        @if ($selected->whatnot_order_id)
                            <button type="button" class="vxr-btn vxr-btn--primary" style="flex:1" x-on:click="copy(@js($selected->whatnot_order_id))">
                                <x-filament::icon icon="heroicon-o-document-duplicate" /> <span x-text="copied ? 'Copied' : 'Copy Order ID'">Copy Order ID</span>
                            </button>
                        @endif
                    </div>
                @endif
            </aside>
        @endif
    </div>
</div>
</x-filament-panels::page>
