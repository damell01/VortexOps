@php
    use App\Filament\Pages\EndOfStreamForm;
    use App\Support\ShowReportingGoLive;
    use Illuminate\Support\Str;

    $user = auth()->user();
    $tz = $user?->timezone ?: config('app.timezone');
    $first = Str::of($user?->streamer?->name ?? $user?->name ?? 'there')->before(' ');
    $next = $this->nextShow();
    $nextStart = $next?->startsAt($tz);
    $n = $this->numbers();
    $counts = $this->counts();
    $reports = $this->reports();
    $money = fn ($v) => '$' . number_format((float) $v, 2);

    // A small picture for each card, picked from the show's title.
    $iconFor = fn (string $t) => match (true) {
        (bool) preg_match('/free|giveaway|givvy|givy|promo/i', $t) => 'heroicon-o-gift',
        (bool) preg_match('/box|pack|sealed|hobby/i', $t) => 'heroicon-o-cube',
        (bool) preg_match('/rapid|fire|banger|1,000|2,000/i', $t) => 'heroicon-o-bolt',
        (bool) preg_match('/slab|grade|psa|\$1/i', $t) => 'heroicon-o-tag',
        (bool) preg_match('/high end|grail|chase|claims/i', $t) => 'heroicon-o-sparkles',
        default => 'heroicon-o-video-camera',
    };
@endphp
<div class="vsh" data-vx-page="streamer-home">
    {{-- Welcome and the next show, with a Pokémon peeking in. --}}
    <header class="vsh-hero">
        <span class="vsh-hero-ball" aria-hidden="true"></span>
        <div class="vsh-hero-copy">
            <p class="vsh-hero-date">{{ \App\Support\BusinessTime::local()->format('l, F j, Y') }}</p>
            <h1>Welcome back, <span class="whitespace-nowrap">{{ $first }}! <span class="vsh-wave" aria-hidden="true">👋</span></span></h1>
            <p>Log your shows and keep your reports up to date.</p>
        </div>
        <div class="vsh-next">
            <span class="vsh-next-icon" aria-hidden="true"><x-heroicon-o-calendar-days class="h-6 w-6" /></span>
            @if ($next)
                <div class="min-w-0 flex-1"
                     @if ($nextStart) x-data="{ at: {{ $nextStart->getTimestampMs() }}, now: Date.now(),
                        get left() { return Math.max(0, this.at - this.now) },
                        get text() { const m = Math.floor(this.left / 60000), d = Math.floor(m / 1440), h = Math.floor(m / 60) % 24; return (d ? d + 'd ' : '') + (d || h ? h + 'h ' : '') + (m % 60) + 'm' } }"
                     x-init="setInterval(() => now = Date.now(), 20000)" @endif>
                    <p class="vsh-eyebrow" @if ($nextStart) x-text="left <= 15 * 60000 ? (left > 0 ? 'You\'re on soon!' : 'Starting now') : 'Next show'" @endif>Next show</p>
                    <h2 title="{{ $next->title }}">{{ $next->title }}</h2>
                    <p class="vsh-when">
                        {{ ($nextStart ?? $next->show_date)?->format('D, M j') }} ·
                        @if ($nextStart)
                            {{ $nextStart->format('g:i A T') }} <span class="vsh-count" x-show="left > 0">· in <b x-text="text"></b></span>
                        @else
                            Time TBD
                        @endif
                    </p>
                </div>
            @else
                <div class="min-w-0 flex-1">
                    <p class="vsh-eyebrow">Next show</p>
                    <h2>No upcoming shows yet</h2>
                    <p class="vsh-when">New shows appear here as soon as you're scheduled.</p>
                </div>
            @endif
            <a href="#vsh-reports-title" wire:click="setTab('upcoming')" class="vsh-next-link">{{ $next ? 'View show' : 'My shows' }} <x-heroicon-m-arrow-right class="h-4 w-4" /></a>
        </div>
        <img class="vsh-hero-mon" src="/img/pokemon/pikachu.gif" alt="" aria-hidden="true">
    </header>

    {{-- Their numbers for a period, with the period picker alongside. --}}
    <div class="vsh-stats">
        @foreach ([
            ['heroicon-o-calendar-days', 'violet', 'Shows ' . $n['label'], number_format($n['shows']), 'Shows you were on, dated in this period.'],
            ['heroicon-o-currency-dollar', 'green', 'Gross sales ' . $n['label'], $money($n['gross']), 'Whatnot sales for your shows dated in this period.'],
            ['heroicon-o-document-check', 'blue', 'Recorded paid', $money($n['paid']), 'Pay already paid out for shows in this period.'],
            ['heroicon-o-clock', 'amber', 'Approved unpaid', $money($n['unpaid']), 'Pay approved on a pay run for shows in this period, not paid yet.'],
        ] as [$icon, $tone, $label, $value, $tip])
            <div class="vsh-stat">
                <span class="vsh-stat-icon vsh-{{ $tone }}"><x-filament::icon :icon="$icon" class="h-5 w-5" /></span>
                <div class="min-w-0">
                    <p class="vsh-stat-label">{{ $label }} <span class="vsh-tip" title="{{ $tip }}" tabindex="0" aria-label="{{ $tip }}"><x-heroicon-o-information-circle class="h-4 w-4" /></span></p>
                    <p class="vsh-stat-value">{{ $value }}</p>
                </div>
            </div>
        @endforeach
        <label class="vsh-period vsh-period--stats">
            <x-heroicon-o-calendar class="h-5 w-5 shrink-0" />
            <span class="sr-only">Period</span>
            <select wire:model.live="period">
                @foreach (\App\Livewire\StreamerHome::PERIODS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
            </select>
        </label>
    </div>

    <livewire:clock-widget :compact="true" :key="'streamer-home-clock'" />

    {{-- Every report, as cards. --}}
    <section class="vsh-reports" aria-labelledby="vsh-reports-title">
        <div class="vsh-reports-head">
            <h2 id="vsh-reports-title">Your show reports</h2>
            @if ($counts['to_complete'] > 0)<span class="vsh-chip">{{ number_format($counts['to_complete']) }} to complete</span>@endif
        </div>
        <div class="vsh-toolbar">
            <nav class="vsh-tabs" aria-label="Report views">
                @foreach (\App\Livewire\StreamerHome::TABS as $key => $label)
                    <button type="button" wire:click="setTab('{{ $key }}')" aria-current="{{ $tab === $key ? 'page' : 'false' }}" @class(['is-active' => $tab === $key])>
                        {{ $label }}@if (in_array($key, ['to_complete', 'changes', 'upcoming'], true)) <span class="vsh-tab-count">{{ number_format($counts[$key]) }}</span>@endif
                    </button>
                @endforeach
            </nav>
            <div class="vsh-tools">
                <label class="vsh-search"><x-heroicon-m-magnifying-glass class="h-5 w-5 text-gray-400" /><span class="sr-only">Search shows</span><input type="search" wire:model.live.debounce.400ms="search" placeholder="Search shows…"></label>
                <label class="vsh-period vsh-range" title="{{ $this->rangeLabel() }}">
                    <x-heroicon-o-calendar class="h-5 w-5 shrink-0" />
                    <span class="sr-only">Show dates</span>
                    <select wire:model.live="range">
                        @foreach (\App\Livewire\StreamerHome::RANGES as $value => $label)<option value="{{ $value }}">{{ $value === $range && $value !== '' ? $this->rangeLabel() : $label }}</option>@endforeach
                    </select>
                </label>
            </div>
        </div>

        <div class="vsh-grid" wire:loading.class="opacity-60" wire:target="setTab,search,range,perPage,gotoPage,nextPage,previousPage">
            @forelse ($reports as $show)
                @php
                    $entry = $show->streamerLogEntry;
                    $items = (int) ($entry?->items_count ?? 0);
                    $gives = (int) ($entry?->giveaway_quantity ?? 0);
                    $filed = $entry?->submitted_at !== null;
                    $url = EndOfStreamForm::getUrl(['showId' => $show->id]);
                    $scheduled = $tab === 'upcoming';
                    $pay = $show->payouts;
                    [$state, $tone, $action, $solid] = match (true) {
                        $scheduled => [$show->show_date?->isToday() ? 'Today' : 'Scheduled', 'violet', null, false],
                        $entry?->status === 'changes_requested' => ['Needs changes', 'rose', 'Fix report', false],
                        $filed && $entry->status === 'admin_approved' => ['Approved', 'green', 'View report', false],
                        $filed => ['Submitted', 'blue', 'View report', false],
                        ! ShowReportingGoLive::covers($show->show_date) => ['Previous show', 'gray', 'View show', false],
                        $entry && ($items > 0 || $entry->draft_saved_at) => ['Draft', 'amber', 'Continue report', false],
                        default => ['Not started', 'rose', 'Log sold items', true],
                    };
                    $saved = $entry && ! $filed ? ($entry->draft_saved_at ?? ($items > 0 ? $entry->updated_at : null)) : null;
                @endphp
                <article class="vsh-card" wire:key="vsh-show-{{ $show->id }}">
                    <div class="vsh-card-top">
                        <span class="vsh-card-icon"><x-filament::icon :icon="$iconFor((string) $show->title)" class="h-6 w-6" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="vsh-meta">{{ $show->show_date?->format('M j, Y') }}@if ($show->channel) · {{ $show->channel->name }}@endif</p>
                            <h3 title="{{ $show->title }}">{{ $show->title ?: 'Show #' . $show->id }}</h3>
                        </div>
                        <span class="vsh-pill vsh-{{ $tone }}">{{ $state }}</span>
                    </div>
                    @unless ($scheduled)
                    @php
                        $gross = (float) ($show->gross_revenue ?? 0);
                        $net = $show->completed_earnings ?? $show->whatnot_net;
                    @endphp
                    {{-- What the show made on Whatnot, then what the report holds. --}}
                    <dl class="vsh-figs vsh-figs--money">
                        <div><dt>Sales</dt><dd>{{ $gross > 0 ? $money($gross) : '—' }}</dd></div>
                        <div><dt>Whatnot net</dt><dd>{{ $net !== null && ($gross > 0 || (float) $net != 0) ? $money($net) : '—' }}</dd></div>
                        @if ($pay->isNotEmpty())
                            <div><dt>Your pay</dt><dd class="vsh-paid">{{ $money($pay->sum('calculated_payout')) }}</dd><p class="vsh-meta">{{ $pay->every(fn ($p) => $p->status === 'paid') ? 'Paid' : 'Awaiting payment' }}</p></div>
                        @endif
                    </dl>
                    <p class="vsh-counts"><span>Items logged <b>{{ number_format($items) }}</b></span><span aria-hidden="true">·</span><span>Giveaways <b>{{ number_format($gives) }}</b></span>@if ($saved)<span class="vsh-saved">Saved {{ $saved->diffForHumans() }}</span>@endif</p>
                    @endunless
                    @if ($action)
                        <a href="{{ $url }}" @class(['vsh-btn', 'is-solid' => $solid])>
                            <x-filament::icon :icon="$solid ? 'heroicon-o-list-bullet' : ($filed ? 'heroicon-o-eye' : 'heroicon-o-document-text')" class="h-5 w-5" />
                            {{ $action }}
                        </a>
                    @else
                        <p class="vsh-later">{{ $show->start_time ? 'Starts ' . $show->start_time->format('g:i A') . ' · ' : '' }}Your report opens once the show airs.</p>
                    @endif
                    @if ($entry?->hasPendingRevisionRequest())
                        <p class="vsh-asked"><x-heroicon-m-chat-bubble-left-ellipsis class="h-4 w-4 shrink-0" /> You asked to reopen this. An admin will reply.</p>
                    @elseif ($entry?->canRequestRevision())
                        <button type="button" class="vsh-ask" wire:click="askForChanges({{ $show->id }})">Need to change something?</button>
                    @endif
                </article>
            @empty
                <div class="vsh-empty">
                    <img src="/img/pokemon/pikachu.gif" alt="" aria-hidden="true">
                    <p class="font-semibold">{{ $tab === 'to_complete' ? "You're all caught up!" : ($tab === 'upcoming' ? 'Nothing scheduled yet' : 'No shows here') }}</p>
                    <p class="vsh-meta">{{ $tab === 'to_complete' ? 'Every show you were on has a report filed.' : ($tab === 'upcoming' ? 'New shows appear here as soon as you are scheduled.' : 'Try another tab or date range.') }}</p>
                </div>
            @endforelse
        </div>

        @if ($reports->total() > 0)
            @php
                $pages = $reports->lastPage();
                $cur = $reports->currentPage();
                $show = collect([1, $cur - 1, $cur, $cur + 1, $pages])->filter(fn ($p) => $p >= 1 && $p <= $pages)->unique()->sort()->values();
            @endphp
            <div class="vsh-pager">
                <p class="vsh-meta">Showing {{ $reports->firstItem() }}–{{ $reports->lastItem() }} of {{ number_format($reports->total()) }} {{ Str::plural('show', $reports->total()) }}</p>
                <nav class="vsh-pages" aria-label="Pages">
                    <button type="button" wire:click="previousPage" @disabled($reports->onFirstPage())><x-heroicon-m-chevron-left class="h-4 w-4" /> Previous</button>
                    @foreach ($show as $i => $p)
                        @if ($i > 0 && $p - $show[$i - 1] > 1)<span aria-hidden="true">…</span>@endif
                        <button type="button" wire:click="gotoPage({{ $p }})" @class(['is-active' => $p === $cur]) aria-current="{{ $p === $cur ? 'page' : 'false' }}">{{ $p }}</button>
                    @endforeach
                    <button type="button" wire:click="nextPage" @disabled(! $reports->hasMorePages())>Next <x-heroicon-m-chevron-right class="h-4 w-4" /></button>
                </nav>
                <label class="vsh-period"><span class="sr-only">Shows per page</span>
                    <select wire:model.live="perPage">@foreach ([6, 12, 24] as $pp)<option value="{{ $pp }}">{{ $pp }} / page</option>@endforeach</select>
                </label>
            </div>
        @endif
    </section>

    {{-- Asking for a filed report back: a centered pop-up. --}}
    @if ($revisionFor && ($asking = $this->revisionShow()))
        <div class="vsh-modal-backdrop" wire:click="cancelRevisionRequest"></div>
        <div class="vsh-modal" role="dialog" aria-modal="true" aria-labelledby="vsh-ask-title" x-data x-on:keydown.escape.window="$wire.cancelRevisionRequest()">
            <h2 id="vsh-ask-title">Ask to change this report</h2>
            <p class="vsh-meta">{{ $asking->title }} · {{ $asking->show_date?->format('M j, Y') }}</p>
            <label class="vsh-field">What needs changing?
                <textarea wire:model="revisionReason" rows="3" placeholder="e.g. I logged the wrong item" x-init="$el.focus()"></textarea>
            </label>
            <p class="vsh-meta">The report stays as filed until an admin reopens it for you.</p>
            <div class="vsh-modal-actions">
                <button type="button" class="vsh-btn" wire:click="cancelRevisionRequest">Cancel</button>
                <button type="button" class="vsh-btn is-solid" wire:click="submitRevisionRequest" wire:loading.attr="disabled" wire:target="submitRevisionRequest">Send request</button>
            </div>
        </div>
    @endif

<style>
    .vsh { --vsh-line: rgb(229 231 235); --vsh-card: #fff; --vsh-muted: rgb(107 114 128); display:flex; flex-direction:column; gap:1rem; }
    .dark .vsh { --vsh-line: rgb(55 65 81); --vsh-card: rgb(17 24 39); --vsh-muted: rgb(156 163 175); }
    .vsh-violet { --c:#7c3aed; } .vsh-green { --c:#059669; } .vsh-blue { --c:#2563eb; } .vsh-amber { --c:#d97706; } .vsh-rose { --c:#e11d48; } .vsh-gray { --c:#6b7280; }
    .dark .vsh-violet { --c:#a78bfa; } .dark .vsh-green { --c:#34d399; } .dark .vsh-blue { --c:#60a5fa; } .dark .vsh-amber { --c:#fbbf24; } .dark .vsh-rose { --c:#fb7185; } .dark .vsh-gray { --c:#9ca3af; }

    .vsh-hero { position:relative; display:flex; flex-wrap:wrap; align-items:center; gap:1.25rem 2rem; padding:1.6rem 11rem 1.6rem 2rem; border-radius:1.25rem; overflow:hidden; color:#fff;
        background: radial-gradient(120% 140% at 85% 0%, #7c3aed 0%, transparent 55%), linear-gradient(110deg, #1e1b4b 0%, #312e81 45%, #4c1d95 100%); box-shadow: 0 18px 40px -24px rgb(76 29 149 / .8); }
    .vsh-hero-ball { position:absolute; left:-2.2rem; top:50%; width:9rem; height:9rem; transform:translateY(-50%); border-radius:50%; opacity:.16;
        background: radial-gradient(circle at 50% 50%, transparent 0 1.2rem, #fff 1.2rem 1.6rem, transparent 1.6rem), linear-gradient(transparent 46%, #fff 46% 54%, transparent 54%); border:.4rem solid #fff; }
    .vsh-hero-copy { position:relative; flex:1 1 20rem; min-width:0; padding-left:1.5rem; }
    .vsh-hero-date { font-size:.85rem; opacity:.85; font-weight:500; }
    .vsh-hero h1 { margin:.2rem 0 .35rem; font-size:clamp(1.6rem, 3vw, 2.4rem); font-weight:800; line-height:1.1; letter-spacing:-.02em; }
    .vsh-hero-copy > p:last-child { opacity:.85; }
    .vsh-wave { display:inline-block; transform-origin: 70% 80%; animation: vsh-wave 1.6s ease-in-out 1 .4s; }
    @keyframes vsh-wave { 0%,100% { transform:rotate(0) } 20%,60% { transform:rotate(14deg) } 40%,80% { transform:rotate(-8deg) } }
    .vsh-next { position:relative; display:flex; align-items:center; gap:1.25rem; padding:1rem 1.25rem; min-width:0; max-width:32rem; flex:1 1 22rem; border:1px solid rgb(255 255 255 / .18); border-radius:1rem; background: rgb(255 255 255 / .08); backdrop-filter: blur(4px); }
    .vsh-next > div:first-child { flex:1; }
    .vsh-eyebrow { font-size:.7rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; opacity:.8; }
    .vsh-next h2 { margin-top:.15rem; font-size:1.1rem; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .vsh-count { display:flex; align-items:baseline; gap:.45rem; margin:.15rem 0; font-size:1.6rem; font-weight:800; font-variant-numeric: tabular-nums; letter-spacing:-.01em; }
    .vsh-count i { font-style:normal; opacity:.6; font-weight:600; }
    .vsh-when { font-size:.8rem; opacity:.85; }
    .vsh-next-link { display:inline-flex; align-items:center; gap:.35rem; padding-left:1.25rem; border-left:1px solid rgb(255 255 255 / .2); font-weight:600; font-size:.9rem; white-space:nowrap; align-self:stretch; }
    .vsh-next-link:hover { text-decoration: underline; }
    .vsh-hero-mon { position:absolute; right:1.5rem; bottom:-.3rem; height:8.25rem; width:auto; image-rendering:pixelated; filter: drop-shadow(0 10px 16px rgb(0 0 0 / .35)); animation: vsh-bob 3.2s ease-in-out 2; }
    @keyframes vsh-bob { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-6px) } }

    .vsh-numbers-head { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:-.25rem; }
    .vsh-numbers-head h2 { font-size:1.05rem; font-weight:700; }
    .vsh-numbers-head .vsh-period { min-height:2.4rem; }
    .vsh-stats { display:grid; gap:.9rem; grid-template-columns: repeat(2, minmax(0,1fr)); }
    @media (min-width: 1000px) { .vsh-stats { grid-template-columns: repeat(4, minmax(0,1fr)); } }
    .vsh-stat { display:flex; align-items:center; gap:.9rem; padding:1rem 1.1rem; border:1px solid var(--vsh-line); border-radius:1rem; background: var(--vsh-card); min-width:0; }
    .vsh-stat-icon { display:grid; place-items:center; flex:none; width:3rem; height:3rem; border-radius:.85rem; color: var(--c); background: color-mix(in srgb, var(--c) 13%, transparent); }
    .vsh-stat-label { font-size:.85rem; color: var(--vsh-muted); }
    .vsh-stat > div { flex:1; }
    .vsh-stat-value { display:flex; flex-wrap:wrap; align-items:center; gap:.15rem .4rem; font-size:clamp(1.1rem, 1.55vw, 1.5rem); line-height:1.2; font-weight:800; font-variant-numeric: tabular-nums; overflow-wrap:anywhere; }
    .vsh-tip { flex:none; display:inline-flex; }
    .vsh-tip { color: var(--vsh-muted); cursor: help; }
    .vsh-period { display:inline-flex; align-items:center; gap:.5rem; align-self:start; min-height:2.75rem; padding:0 .25rem 0 .85rem; border:1px solid var(--vsh-line); border-radius:.8rem; background: var(--vsh-card); }
    .vsh-period select { border:0 !important; background-color:transparent !important; outline:0; font-size:.875rem; font-weight:500; padding:.5rem 2rem .5rem .25rem; box-shadow:none; }
    .vsh-period select:focus { box-shadow:none; outline:none; }
    .dark .vsh-period select { color:#e5e7eb; }

    .vsh-reports { display:flex; flex-direction:column; gap:1rem; }
    .vsh-reports-head { display:flex; align-items:center; gap:.75rem; }
    .vsh-reports-head h2 { font-size:1.3rem; font-weight:800; }
    .vsh-chip { padding:.2rem .6rem; border-radius:.5rem; font-size:.75rem; font-weight:600; color: #4c1d95; background: #ede9fe; }
    .dark .vsh-chip { color:#ddd6fe; background: rgb(76 29 149 / .45); }
    .vsh-toolbar { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; }
    .vsh-tabs { display:flex; gap:.35rem; overflow-x:auto; max-width:100%; }
    .vsh-tabs button { display:inline-flex; align-items:center; gap:.45rem; padding:.55rem .95rem; border-radius:.7rem; font-size:.875rem; font-weight:600; color: var(--vsh-muted); white-space:nowrap; transition: background-color .15s, color .15s; }
    .vsh-tabs button:hover { color: rgb(17 24 39); }
    .dark .vsh-tabs button:hover { color:#fff; }
    .vsh-tabs button.is-active { color:#fff; background: linear-gradient(135deg, #6d28d9, #7c3aed); box-shadow: 0 6px 14px -8px rgb(109 40 217 / .9); }
    .vsh-tab-count { min-width:1.4rem; padding:0 .4rem; border-radius:999px; font-size:.72rem; line-height:1.4rem; text-align:center; background: rgb(0 0 0 / .07); }
    .dark .vsh-tab-count { background: rgb(255 255 255 / .1); }
    .vsh-tabs button.is-active .vsh-tab-count { background:#fff; color:#4c1d95; }
    .vsh-tools { display:flex; flex-wrap:wrap; gap:.6rem; }
    .vsh-search { display:flex; align-items:center; gap:.5rem; min-height:2.75rem; padding:0 .85rem; border:1px solid var(--vsh-line); border-radius:.8rem; background: var(--vsh-card); min-width:13rem; }
    .vsh-search input { border:0; background:transparent; font-size:.875rem; padding:.5rem 0; box-shadow:none; min-width:0; flex:1; }
    .vsh-search input:focus { outline:none; box-shadow:none; }
    .vsh-search:focus-within { border-color:#8b5cf6; }

    .vsh-grid { display:grid; gap:1rem; grid-template-columns: 1fr; transition: opacity .15s; }
    @media (min-width: 768px) { .vsh-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
    @media (min-width: 1200px) { .vsh-grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }
    .vsh-card { display:flex; flex-direction:column; gap:.9rem; padding:1.1rem 1.2rem 1.2rem; border:1px solid var(--vsh-line); border-radius:1rem; background: var(--vsh-card); transition: box-shadow .15s, transform .15s; min-width:0; }
    .vsh-card:hover { box-shadow: 0 12px 26px -16px rgb(76 29 149 / .45); transform: translateY(-1px); }
    .vsh-card-top { display:flex; align-items:flex-start; gap:.9rem; padding-bottom:.85rem; border-bottom:1px solid var(--vsh-line); }
    .vsh-card-icon { display:grid; place-items:center; flex:none; width:3rem; height:3rem; border-radius:.8rem; color:#7c3aed; background:#f1ecff; }
    .dark .vsh-card-icon { color:#c4b5fd; background: rgb(76 29 149 / .35); }
    .vsh-card h3 { font-size:1.02rem; font-weight:700; line-height:1.3; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .vsh-meta { font-size:.78rem; color: var(--vsh-muted); }
    .vsh-pill { flex:none; padding:.2rem .6rem; border-radius:.45rem; font-size:.72rem; font-weight:700; color: var(--c); background: color-mix(in srgb, var(--c) 12%, transparent); white-space:nowrap; }
    .vsh-figs { display:grid; grid-template-columns: 1fr 1fr; }
    .vsh-figs > div + div { padding-left:1rem; border-left:1px solid var(--vsh-line); }
    .vsh-figs dt { font-size:.8rem; color: var(--vsh-muted); }
    .vsh-figs dd { font-size:1.25rem; font-weight:800; font-variant-numeric: tabular-nums; }
    .vsh-figs--money { padding:.6rem .75rem; margin:0 -.1rem; border-radius:.75rem; background: color-mix(in srgb, #7c3aed 5%, var(--vsh-card)); }
    .vsh-figs--money dd { font-size:1.02rem; overflow-wrap:anywhere; }
    .vsh-btn { display:flex; align-items:center; justify-content:center; gap:.55rem; min-height:2.75rem; margin-top:auto; border:1.5px solid #7c3aed; border-radius:.75rem; font-weight:700; color:#6d28d9; background: var(--vsh-card); transition: background-color .15s, box-shadow .15s; }
    .dark .vsh-btn { color:#c4b5fd; border-color:#8b5cf6; }
    .vsh-btn:hover { background: color-mix(in srgb, #7c3aed 7%, var(--vsh-card)); }
    .vsh-btn.is-solid { color:#fff; border-color:transparent; background: linear-gradient(135deg, #6d28d9, #7c3aed); box-shadow: 0 8px 18px -10px rgb(109 40 217 / .9); }
    .vsh-btn.is-solid:hover { background: linear-gradient(135deg, #5b21b6, #6d28d9); }
    .vsh-figs:has(> div:nth-child(3)) { grid-template-columns: repeat(3, minmax(0,1fr)); }
    .vsh-figs > div:nth-child(3) { padding-left:1rem; border-left:1px solid var(--vsh-line); }
    .vsh-paid { color:#059669; }
    .dark .vsh-paid { color:#34d399; }
    .vsh-later { margin-top:auto; padding:.7rem; border-radius:.75rem; text-align:center; font-size:.85rem; color: var(--vsh-muted); background: color-mix(in srgb, #7c3aed 6%, var(--vsh-card)); }
    .vsh-ask { align-self:center; margin-top:-.35rem; font-size:.8rem; font-weight:600; color: var(--vsh-muted); }
    .vsh-ask:hover { color:#7c3aed; text-decoration: underline; }
    .vsh-asked { display:flex; align-items:center; gap:.4rem; margin-top:-.35rem; font-size:.8rem; color:#2563eb; }
    .vsh-modal-backdrop { position:fixed; inset:0; z-index:90; background: rgb(15 23 42 / .5); backdrop-filter: blur(3px); }
    .vsh-modal { position:fixed; z-index:91; top:50%; left:50%; transform:translate(-50%,-50%); width:min(30rem, calc(100vw - 2rem)); display:flex; flex-direction:column; gap:.75rem; padding:1.4rem; border-radius:1.1rem; background: var(--vsh-card); border:1px solid var(--vsh-line); box-shadow: 0 24px 60px -18px rgb(46 16 101 / .45); }
    .vsh-modal h2 { font-size:1.15rem; font-weight:800; }
    .vsh-field { display:flex; flex-direction:column; gap:.35rem; font-size:.85rem; font-weight:600; }
    .vsh-field textarea { border:1px solid var(--vsh-line); border-radius:.75rem; padding:.65rem .8rem; font-weight:400; background: var(--vsh-card); }
    .vsh-field textarea:focus { outline:none; border-color:#8b5cf6; box-shadow:0 0 0 3px rgb(139 92 246 / .2); }
    .vsh-modal-actions { display:flex; justify-content:flex-end; gap:.5rem; }
    .vsh-modal-actions .vsh-btn { margin-top:0; padding:0 1.1rem; }
    .vsh-empty { grid-column: 1 / -1; display:flex; flex-direction:column; align-items:center; gap:.25rem; padding:2.5rem 1rem; border:1px dashed var(--vsh-line); border-radius:1rem; text-align:center; }
    .vsh-empty img { height:4rem; image-rendering:pixelated; margin-bottom:.4rem; }

    .vsh-pager { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; }
    .vsh-pages { display:flex; flex-wrap:wrap; align-items:center; gap:.4rem; }
    .vsh-pages button { display:inline-flex; align-items:center; gap:.3rem; min-width:2.6rem; min-height:2.6rem; padding:0 .8rem; justify-content:center; border:1px solid var(--vsh-line); border-radius:.65rem; background: var(--vsh-card); font-size:.875rem; font-weight:600; }
    .vsh-pages button.is-active { color:#fff; border-color:transparent; background:#7c3aed; }
    .vsh-pages button:disabled { opacity:.45; cursor:default; }
    .vsh-pages span { padding:0 .25rem; color: var(--vsh-muted); }

    @media (max-width: 760px) {
        .vsh-hero { padding:1.25rem 1.25rem 7rem; }
        .vsh-hero-copy { padding-left:0; }
        .vsh-hero-ball { display:none; }
        .vsh-hero-mon { height:6.5rem; right:1rem; }
        .vsh-next { flex-direction:column; align-items:flex-start; gap:.5rem; }
        .vsh-next > div:first-child { width:100%; }
        .vsh-next-link { border-left:0; padding-left:0; }
        .vsh-stats { grid-template-columns: 1fr 1fr; }
        .vsh-stat { padding:.85rem; gap:.65rem; }
        .vsh-stat-icon { width:2.4rem; height:2.4rem; }
        .vsh-stat-value { font-size:1.15rem; }
        .vsh-tools { width:100%; display:grid; grid-template-columns:1fr; }
        .vsh-search { min-width:0; }
        .vsh-tools .vsh-period { width:100%; grid-column: 1 / -1; }
        .vsh-period { padding:0; border:0; background:transparent; min-height:0; }
        .vsh-period > svg { display:none; }
        .vsh-period select { flex:1; }
    }
    @media (prefers-reduced-motion: reduce) { .vsh-wave, .vsh-hero-mon { animation:none; } .vsh-card, .vsh-card:hover { transition:none; transform:none; } }

    /* ── Compact pass: the first row of show cards should be on screen without scrolling. ── */
    .vsh { gap:.85rem; }
    .vsh-hero { padding:1rem 6.5rem 1rem 1.75rem; gap:.85rem 1.5rem; border-radius:1.1rem; }
    .vsh-hero-ball { width:6.5rem; height:6.5rem; left:-2rem; border-width:.3rem; }
    .vsh-hero-copy { flex:1 1 18rem; padding-left:1.1rem; }
    .vsh-hero-date { font-size:.78rem; }
    .vsh-hero h1 { margin:.05rem 0 .15rem; font-size:clamp(1.3rem, 2.1vw, 1.8rem); }
    .vsh-hero-copy > p:last-child { font-size:.88rem; }
    .vsh-next { flex:1 1 24rem; max-width:34rem; gap:.85rem; padding:.65rem .9rem; border-radius:.9rem; }
    .vsh-next-icon { display:grid; place-items:center; flex:none; width:2.5rem; height:2.5rem; border-radius:.7rem; background: rgb(255 255 255 / .12); }
    .vsh-next h2 { margin-top:.05rem; font-size:1rem; }
    .vsh-when { font-size:.78rem; }
    .vsh-count { display:inline; margin:0; font-size:inherit; font-weight:500; }
    .vsh-count b { font-weight:800; font-variant-numeric: tabular-nums; }
    .vsh-next-link { padding-left:1rem; font-size:.85rem; }
    .vsh-hero-mon { height:3.75rem; right:1.4rem; bottom:.5rem; animation:none; filter: drop-shadow(0 4px 8px rgb(0 0 0 / .35)); }

    .vsh-stats { gap:.75rem; }
    @media (min-width: 1000px) { .vsh-stats { grid-template-columns: repeat(4, minmax(0,1fr)) auto; } }
    .vsh-stats > .vsh-period--stats { grid-column: 1 / -1; justify-self:end; }
    @media (min-width: 1000px) { .vsh-stats > .vsh-period--stats { grid-column:auto; align-self:center; } }
    .vsh-stat { gap:.75rem; padding:.7rem .9rem; border-radius:.9rem; }
    .vsh-stat-icon { width:2.4rem; height:2.4rem; border-radius:.7rem; }
    .vsh-stat-label { display:flex; align-items:center; gap:.3rem; font-size:.78rem; }
    .vsh-stat-value { font-size:clamp(1.05rem, 1.4vw, 1.35rem); }
    .vsh-period { min-height:2.5rem; }

    .vsh .vx-clock .vxw-card-body { padding:.6rem 1rem; }
    .vsh .vx-clock .vx-clock-big { font-size:17px; }
    .vsh .vx-clock .vx-clock-dot { width:2.4rem; height:2.4rem; }
    .vsh .vx-clock .vxw-eyebrow { display:none; }

    .vsh-reports { gap:.75rem; }
    .vsh-reports-head h2 { font-size:1.15rem; }
    .vsh-tabs button { padding:.45rem .8rem; border-radius:.6rem; }
    .vsh-tabs button.is-active { color:#6d28d9; background:#f1ecff; box-shadow: inset 0 -2px 0 #7c3aed; }
    .dark .vsh-tabs button.is-active { color:#ddd6fe; background: rgb(124 58 237 / .2); box-shadow: inset 0 -2px 0 #a78bfa; }
    .vsh-tabs button.is-active .vsh-tab-count { background:#7c3aed; color:#fff; }
    .vsh-search, .vsh-tools .vsh-period { min-height:2.5rem; }

    .vsh-grid { gap:.85rem; }
    .vsh-card { gap:.6rem; padding:.85rem 1rem .9rem; }
    .vsh-card-top { gap:.75rem; padding-bottom:.6rem; }
    .vsh-card-icon { width:2.5rem; height:2.5rem; border-radius:.7rem; }
    .vsh-card h3 { font-size:.98rem; }
    .vsh-figs--money { padding:0; margin:0; background:none; }
    .vsh-figs--money dt { font-size:.75rem; }
    .vsh-figs--money dd { font-size:1.05rem; }
    .vsh-counts { display:flex; flex-wrap:wrap; align-items:center; gap:.2rem .5rem; font-size:.8rem; color: var(--vsh-muted); }
    .vsh-counts b { color: rgb(17 24 39); font-weight:700; }
    .dark .vsh-counts b { color:#f3f4f6; }
    .vsh-saved { margin-left:auto; font-size:.75rem; }
    .vsh-btn { min-height:2.5rem; border-radius:.65rem; font-size:.92rem; }
    .vsh-later { padding:.55rem; font-size:.8rem; }
    /* The dashboard has no page title of its own; drop the empty header's space. */
    /* The dashboard has no page title, so its header padding is just empty space. */
    .fi-page-header-main-ctn:has(.vsh) { padding-top:14px; }
    .fi-page-content:has(.vsh) { padding-top:0; }
    @media (max-width: 760px) {
        .vsh-hero { padding:1rem 4.25rem 1rem 1rem; }
        .vsh-hero-mon { height:2.75rem; top:.8rem; bottom:auto; right:.8rem; }
        .vsh-next { display:grid; grid-template-columns:auto minmax(0,1fr); align-items:center; gap:.4rem .75rem; margin-right:-3.25rem; }
        .vsh-next > div { min-width:0; }
        .vsh-next-link { grid-column:2; justify-self:start; margin:0; padding:0; border-left:0; }
        .vsh-stat { padding:.65rem .75rem; }
        .vsh-stat-icon { display:none; }
        .vsh-stat-value { font-size:1.05rem; white-space:nowrap; overflow-wrap:normal; }
        .vsh-stats > .vsh-period--stats { justify-self:stretch; }
    }
</style>
</div>
