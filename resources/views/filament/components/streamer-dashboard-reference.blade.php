@include('filament.pages.partials.mockup-workspace-styles')
@php
    $groups = $myRecentShows->groupBy(fn ($show) => $show->show_date?->isToday() ? 'today' : ($show->show_date?->isYesterday() ? 'yesterday' : 'earlier'));
    $initialDay = $groups->has('today') ? 'today' : ($groups->has('yesterday') ? 'yesterday' : 'earlier');
    $next = $myUpcomingShows->first(fn ($show) => !$myRecentShows->contains('id', $show->id));
@endphp
<div class="vx-stream-home" x-data="{day: @js($initialDay)}">
    @php
        $toLog = collect(['today', 'yesterday', 'earlier'])->flatMap(fn ($d) => $groups->get($d, collect()))
            ->first(fn ($show) => ! ($show->streamerLogEntry?->submitted_at && $show->streamerLogEntry?->status !== 'changes_requested'));
        $todayCount = $groups->get('today', collect())->count();
    @endphp
    {{-- Welcome: who, when, the three numbers that matter, and the one thing to do next. --}}
    <header class="vx-home-hero">
        <span class="vx-home-hero-ball" aria-hidden="true"></span>
        <div class="vx-home-welcome">
            <p class="vx-home-hero-date"><x-heroicon-o-calendar/> {{ now()->format('l, M j, Y') }}</p>
            <h1>Welcome back, {{ auth()->user()?->streamer?->name ?? auth()->user()?->name }} <span class="vx-home-wave" aria-hidden="true">👋</span></h1>
            <p>Choose your show and log the items you sold.</p>
            <div class="vx-home-hero-stats">
                <span><b>{{ $todayCount }}</b> {{ \Illuminate\Support\Str::plural('show', $todayCount) }} today</span>
                <span><b>{{ $reportsDue ?? 0 }}</b> to log</span>
                <span><b>{{ $monthShowCount ?? 0 }}</b> this month</span>
            </div>
        </div>
        @if ($toLog)
            <a class="vx-home-hero-cta" href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $toLog->id]) }}">
                <img class="vx-sprite vx-home-hero-pika" src="/img/pokemon/pikachu.gif" alt="" aria-hidden="true">
                <span class="vx-home-hero-cta-icon"><x-heroicon-o-plus/></span>
                <span><b>Log your next show</b><small>{{ \Illuminate\Support\Str::limit($toLog->title, 42) }}</small></span>
                <x-heroicon-m-chevron-right class="vx-home-hero-cta-arrow"/>
            </a>
        @endif
    </header>
    <livewire:clock-widget :compact="true" :key="'streamer-home-clock'" />
    <h2 class="vx-home-section-title">Log Items for a Recent Show</h2>
    <div class="vx-home-columns">
        <main class="vx-home-main">
            <div class="vx-home-tabs" role="tablist" aria-label="Recent show day">
                @foreach(['today'=>'Today','yesterday'=>'Yesterday','earlier'=>'Earlier Shows'] as $key=>$label)
                    <button type="button" role="tab" @click="day='{{ $key }}'" :aria-selected="day==='{{ $key }}'" :class="{'is-active':day==='{{ $key }}'}">{{ $label }} <span>{{ $groups->get($key, collect())->count() }}</span></button>
                @endforeach
            </div>
            @foreach(['today','yesterday','earlier'] as $day)
                <div x-show="day==='{{ $day }}'" @if($day !== $initialDay) x-cloak @endif class="vx-home-show-list">
                @forelse($groups->get($day, collect()) as $show)
                    @php
                        $report=$show->streamerLogEntry;
                        $locked=$report?->submitted_at && $report?->status !== 'changes_requested';
                        $status=$report?->status === 'changes_requested' ? 'Needs changes' : ($locked ? ($report?->status === 'admin_approved' ? 'Approved' : 'Submitted') : (($report?->items_count ?? 0)>0 ? 'Report in progress' : 'Needs items logged'));
                        $tone=$report?->status === 'changes_requested' || (!$locked && !($report?->items_count)) ? 'danger' : ($locked ? 'success' : 'warning');
                        $url=\App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$show->id]);
                    @endphp
                    <article class="vx-home-card vx-home-show" wire:key="home-show-{{ $show->id }}">
                        <div class="vx-home-show-copy"><p class="vx-home-date"><b>{{ strtoupper($day === 'earlier' ? $show->show_date?->format('D') : $day) }}</b> {{ $show->show_date?->format('M j, Y') }} @if($show->start_time) · {{ $show->start_time->format('g:i A') }} @endif @if($show->isLive())<x-vx.live />@endif</p><h3>{{ $show->title }}</h3><p>@if($show->channel)<x-vx.channel :name="$show->channel->name" />@endif</p><div class="vx-home-item-meta"><b>{{ $report?->items_count ?? 0 }} items logged</b><span>{{ (int)($report?->giveaway_quantity ?? 0) }} giveaway units</span>@if($report)<span>Saved {{ $report->updated_at?->diffForHumans() }}</span>@endif</div><p class="vx-home-help">Add sold items and giveaways as you go. Submit your report when finished.</p></div>
                        <span class="vx-home-badge {{ $tone }}">{{ $status }}</span>
                        <div class="vx-home-show-actions"><a href="{{ $url }}" class="vx-home-button primary"><x-heroicon-o-clipboard-document-list/> {{ $locked ? 'View Report' : 'Log Sold Items' }}</a>@if(!$locked && $report)<a href="{{ $url }}" class="vx-home-button">Continue Report</a>@endif</div>
                    </article>
                @empty
                    <section class="vx-home-card vx-home-empty"><x-heroicon-o-calendar-days/><h3>No {{ $day === 'earlier' ? 'earlier' : $day }} shows</h3><p>Your assigned shows appear here when they start.</p></section>
                @endforelse
                </div>
            @endforeach
            <section class="vx-home-card"><div class="vx-home-section-heading"><h2><x-heroicon-o-document-text/> Recent Item Entries</h2><a href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('index') }}">View All Logs →</a></div><div class="vx-home-entries">
                @forelse($recentItemEntries ?? [] as $item)
                    <a class="vx-home-entry" href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId'=>$item->logEntry->show_id]) }}"><b>{{ $item->item_name ?: $item->inventoryItem?->name ?: 'Unmatched item' }}</b><span>Qty {{ $item->quantity }}</span><span class="vx-home-badge {{ $item->disposition === 'sold' ? 'success' : '' }}">{{ \App\Models\StreamerLogItem::DISPOSITIONS[$item->disposition] ?? $item->disposition }}</span><span>{{ $item->logEntry?->show?->title }}</span><small>{{ $item->created_at?->diffForHumans() }}</small></a>
                @empty<p class="vx-home-empty">Your latest item entries will appear here.</p>@endforelse
            </div></section>
        </main>
        <aside class="vx-home-sidebar">
            <section class="vx-home-card"><h2><x-heroicon-o-exclamation-triangle/> Needs Your Attention</h2><a class="vx-home-attention" href="{{ \App\Filament\Pages\StreamerShows::getUrl() }}"><div><b>{{ $reportsDue ?? 0 }} reports to review</b><p>Open your shows and finish outstanding reports.</p></div><span>Review →</span></a><a class="vx-home-attention" href="{{ \App\Filament\Resources\StreamerLogResource::getUrl('index') }}"><div><b>{{ $changesRequested ?? 0 }} reports need changes</b><p>Review feedback and update your report.</p></div><span>Review →</span></a></section>
            <section class="vx-home-card"><h2><x-heroicon-o-calendar-days/> Next Scheduled Show</h2>@if($next)<p>{{ $next->show_date?->format('D, M j, Y') }} @if($next->start_time) · {{ $next->start_time->format('g:i A') }} @endif</p><h3 class="mt-3">{{ $next->title }}</h3>@if($next->isLive())<x-vx.live />@endif<p>@if($next->channel)<x-vx.channel :name="$next->channel->name" />@endif</p>@else<p>No upcoming shows assigned.</p>@endif<a class="vx-home-footer" href="{{ \App\Filament\Pages\StreamerShows::getUrl() }}">View My Shows →</a></section>
            <details class="vx-home-card" open><img class="vx-sprite vx-zard" src="/img/pokemon/charizard.gif" alt="" aria-hidden="true"><summary>My Performance This Month</summary><div class="vx-home-performance">@foreach(['Shows'=>$monthShowCount ?? 0,'Gross Sales'=>'$'.number_format($monthGross ?? 0,2),'Recorded Paid'=>'$'.number_format($recordedPaid ?? 0,2),'Approved Unpaid'=>'$'.number_format($approvedUnpaid ?? 0,2)] as $label=>$value)<div><p>{{ $label }}</p><strong>{{ $value }}</strong></div>@endforeach</div><p class="vx-home-help">Sales reflect this month’s shows. Payout totals reflect your recorded payout statuses.</p></details>
        </aside>
    </div>
</div>
