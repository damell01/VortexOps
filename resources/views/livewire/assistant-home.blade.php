@php
    $user = auth()->user();
    $money = fn ($v) => '$' . number_format((float) $v, 2);
    $local = \App\Support\BusinessTime::local();
    $greeting = $local->hour < 12 ? 'Good morning' : ($local->hour < 17 ? 'Good afternoon' : 'Good evening');
    $streamerNames = $this->streamers->pluck('name');
    $showLine = function ($show) {
        $when = $show->show_date?->format('D, M j') . ($show->start_time ? ' · ' . \Illuminate\Support\Carbon::parse($show->start_time)->format('g:i A') : '');
        return [$when, $show->streamers->pluck('name')->implode(' + '), $show->channel?->name];
    };
    $canInventory = \App\Filament\Resources\InventoryItemResource::canAccess();
    $canTime = \App\Filament\Pages\Timekeeping::canAccess();
    $canBoard = \App\Filament\Pages\ShowLeaderboard::canAccess();
@endphp
<div class="vxw vx-assistant-home space-y-4" data-vx-page="assistant-home">
    <style>
        .vx-ah-cards{display:grid;gap:10px;padding:14px;grid-template-columns:repeat(auto-fill,minmax(230px,1fr))}
        .vx-ah-card{display:flex;gap:12px;align-items:flex-start;min-width:0;padding:12px;border:1px solid var(--vxw-border);border-radius:var(--vxw-radius-sm);background:var(--vxw-surface)}
        .vx-ah-date{flex:none;display:flex;flex-direction:column;align-items:center;width:48px;padding:6px 0;border-radius:10px;background:var(--vxw-surface-2);font-size:10px;line-height:1.2;text-transform:uppercase;color:var(--vxw-muted, #6b7280)}
        .vx-ah-date b{font-size:20px;line-height:1.1;color:var(--vxw-text)}
        .vx-ah-title{font-weight:600;font-size:13px;line-height:1.3;color:var(--vxw-text);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .vx-ah-meta{font-size:12px;color:var(--vxw-muted, #6b7280);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    </style>
    <section class="vxw-card">
        <div class="vxw-card-body">
            <div class="vxw-eyebrow">Streamer assistant</div>
            <h2 class="vxw-h1">{{ $greeting }}, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}</h2>
            <p class="vxw-meta mt-1">
                @if($streamerNames->isNotEmpty())
                    You're supporting <strong>{{ $streamerNames->implode(', ', ' and ') }}</strong>.
                @else
                    You aren't assigned to a streamer yet — ask an admin to add you on your profile.
                @endif
            </p>
        </div>
        <div class="vxw-stats">
            <div class="vxw-stat"><div class="vxw-stat-label">Hours this week</div><div class="vxw-stat-value">{{ number_format($this->hours['week'], 2) }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Shifts this week</div><div class="vxw-stat-value">{{ $this->hours['shifts'] }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Upcoming shows</div><div class="vxw-stat-value">{{ $this->upcoming->count() }}</div></div>
            <div class="vxw-stat"><div class="vxw-stat-label">Paid this year</div><div class="vxw-stat-value">{{ $money($this->pay['year']) }}</div></div>
        </div>
    </section>

    @if($canTime)
        <livewire:clock-widget :compact="true" :key="'assistant-home-clock'" />
        @if($this->hours['review'])
            <div class="vxw-alert vxw-alert--warn"><x-filament::icon icon="heroicon-o-exclamation-triangle" /><div>{{ $this->hours['review'] }} {{ \Illuminate\Support\Str::plural('shift', $this->hours['review']) }} this week {{ $this->hours['review'] === 1 ? 'needs' : 'need' }} an admin's review before it counts toward pay.</div></div>
        @endif
    @endif

    @php $canCalendar = \App\Filament\Pages\Shows::canAccess(); @endphp
    @foreach(['upcoming' => ['Upcoming shows', 'Your streamers\' next shows.', 'heroicon-o-calendar', 'No upcoming shows scheduled for your streamers.'], 'recent' => ['Recent shows', 'The last two weeks.', 'heroicon-o-video-camera', 'No shows in the last two weeks.']] as $key => [$heading, $sub, $icon, $none])
        <section class="vxw-card">
            <div class="vxw-card-head">
                <div><h3 class="vxw-h2">{{ $heading }}</h3><p class="vxw-sub">{{ $sub }}</p></div>
                @if($loop->first && $canCalendar)<a class="vxw-btn vxw-btn--ghost vxw-btn--sm" href="{{ \App\Filament\Pages\Shows::getUrl() }}"><x-filament::icon icon="heroicon-m-calendar-days" /> Open calendar</a>@endif
            </div>
            @if($this->{$key}->isEmpty())
                <div class="vxw-empty"><x-filament::icon :icon="$icon" /><div class="vxw-empty-text">{{ $none }}</div></div>
            @else
                <div class="vx-ah-cards">
                    @foreach($this->{$key} as $show)
                        @php [$when, $hosts, $channel] = $showLine($show); @endphp
                        <article class="vx-ah-card" wire:key="{{ $key }}-{{ $show->id }}">
                            <div class="vx-ah-date"><span>{{ $show->show_date?->format('M') }}</span><b>{{ $show->show_date?->format('j') }}</b><span>{{ $show->show_date?->format('D') }}</span></div>
                            <div class="min-w-0">
                                <div class="vx-ah-title" title="{{ $show->title }}">{{ $show->title }}</div>
                                <div class="vx-ah-meta">{{ $show->start_time ? \Illuminate\Support\Carbon::parse($show->start_time)->format('g:i A') : 'Time TBD' }} · {{ $hosts }}</div>
                                @if($channel)<div class="vx-ah-meta">{{ $channel }}</div>@endif
                                @if($show->isLive())<span class="vxw-pill vxw-pill--ok vxw-pill--dot mt-1" style="height:20px;font-size:11px">Live</span>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    <div class="vxw-split">
        @if($canInventory)
            <section class="vxw-card">
                <div class="vxw-card-head"><div><h3 class="vxw-h2">Inventory</h3><p class="vxw-sub">Stock where your streamers keep it. Look-up only.</p></div></div>
                <div class="vxw-stats">
                    <div class="vxw-stat"><div class="vxw-stat-label">Items in stock</div><div class="vxw-stat-value">{{ number_format($this->inventory['items']) }}</div></div>
                    <div class="vxw-stat"><div class="vxw-stat-label">Units</div><div class="vxw-stat-value">{{ number_format($this->inventory['units']) }}</div></div>
                </div>
                <div class="vxw-card-foot"><a class="vxw-btn vxw-btn--sm" href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('index') }}">Look up inventory</a></div>
            </section>
        @endif

        <section class="vxw-card">
            <div class="vxw-card-head"><div><h3 class="vxw-h2">Your pay</h3><p class="vxw-sub">Pay runs marked paid. Hours become pay once a run is approved.</p></div></div>
            <div class="vxw-rows">
                @forelse($this->pay['last'] as $run)
                    <div class="vxw-row">
                        <div class="vxw-row-main"><span class="vxw-row-title">{{ $run['from']?->format('M j') }} – {{ $run['to']?->format('M j, Y') }}</span></div>
                        <div class="vxw-row-side"><div class="vxw-fig"><div class="vxw-fig-label">Paid</div><div class="vxw-fig-value" style="font-size:15px">{{ $money($run['amount']) }}</div></div></div>
                    </div>
                @empty
                    <div class="vxw-empty"><x-filament::icon icon="heroicon-o-banknotes" /><div class="vxw-empty-text">No paid pay runs yet.</div></div>
                @endforelse
            </div>
        </section>
    </div>

    @if($canTime || $canBoard)
        <div class="flex flex-wrap gap-2">
            @if($canTime)<a class="vxw-btn vxw-btn--ghost vxw-btn--sm" href="{{ \App\Filament\Pages\Timekeeping::getUrl() }}"><x-filament::icon icon="heroicon-m-clock" /> My timesheet</a>@endif
            @if($canBoard)<a class="vxw-btn vxw-btn--ghost vxw-btn--sm" href="{{ \App\Filament\Pages\ShowLeaderboard::getUrl() }}"><x-filament::icon icon="heroicon-m-trophy" /> Show leaderboard</a>@endif
        </div>
    @endif
</div>
