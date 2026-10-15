@php
    $tz = auth()->user()?->timezone ?: \App\Support\BusinessTime::zone();
    $reasonIcons = ['preparation' => 'heroicon-m-clipboard-document-list', 'cleanup' => 'heroicon-m-sparkles', 'packing' => 'heroicon-m-archive-box', 'meeting' => 'heroicon-m-user-group', 'other' => 'heroicon-m-ellipsis-horizontal-circle'];
    $mineCount = collect($picker)->where('mine', true)->count();
@endphp
<section class="vxw-card vx-clock" data-vx="clock-widget" style="min-width:0;max-width:100%"
    x-data="{
        picker: false,
        more: @js(! $compact || $changing || $selected || $reason !== ''),
        tab: @js($isReviewer && $mineCount === 0 ? 'all' : 'mine'),
        q: '',
        shows: @js($picker),
        get list() {
            const q = this.q.trim().toLowerCase();
            return this.shows.filter(s => (this.tab === 'all' || s.mine) && (! q || (s.title + ' ' + (s.channel || '') + ' ' + s.day).toLowerCase().includes(q)));
        },
        get groups() {
            const out = [];
            for (const s of this.list) {
                const last = out[out.length - 1];
                if (last && last.key === s.day_key) last.items.push(s); else out.push({ key: s.day_key, day: s.day, items: [s] });
            }
            return out;
        },
        open() { this.q = ''; this.picker = true; this.$nextTick(() => this.$refs.search?.focus()); },
        choose(id) { this.picker = false; $wire.pickShow(id); },
    }"
    @keydown.escape.window="picker = false">

    <style>
        .vx-clock .vx-clock-status { display:flex; align-items:center; gap:14px; min-width:0 }
        .vx-clock .vx-clock-dot { position:relative; display:flex; height:48px; width:48px; flex:none; align-items:center; justify-content:center; border-radius:9999px; background:var(--vxw-sunken); color:var(--vxw-muted) }
        .vx-clock .vx-clock-dot.is-in { background:rgb(16 185 129 / .14); color:#059669 }
        .vx-clock .vx-clock-dot.is-in::after { content:''; position:absolute; top:2px; right:2px; height:11px; width:11px; border-radius:9999px; background:#10b981; box-shadow:0 0 0 3px var(--vxw-surface); animation: vxClockPulse 1.8s ease-in-out infinite }
        @keyframes vxClockPulse { 50% { opacity:.35 } }
        .vx-clock .vx-clock-big { font-size:22px; font-weight:700; line-height:1.2; letter-spacing:-.01em; font-variant-numeric:tabular-nums }
        .vx-clock .vx-clock-stats { display:flex; flex-wrap:wrap; gap:6px 28px; padding:0 4px }
        .vx-clock .vx-clock-stat { font-size:20px; font-weight:700; font-variant-numeric:tabular-nums; letter-spacing:-.01em }
        .vx-clock .vx-clock-section { margin-top:18px; padding-top:16px; border-top:1px solid var(--vxw-border) }
        .vx-clock .vx-clock-label { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:var(--vxw-muted); margin-bottom:8px }
        .vx-clock .vx-clock-pick { display:flex; width:100%; align-items:center; gap:12px; border:1.5px dashed var(--vxw-border-strong); border-radius:12px; padding:12px 14px; text-align:left; background:var(--vxw-surface-2); transition:border-color .15s, background .15s }
        .vx-clock .vx-clock-pick:hover { border-color:rgb(124 58 237 / .6); background:rgb(124 58 237 / .05) }
        .vx-clock .vx-clock-chosen { display:flex; align-items:center; gap:12px; border:1.5px solid rgb(124 58 237 / .45); background:rgb(124 58 237 / .06); border-radius:12px; padding:12px 14px }
        .vx-clock .vx-clock-reasons { display:flex; flex-wrap:wrap; gap:8px }
        .vx-clock .vx-clock-reason { display:inline-flex; align-items:center; gap:6px; border:1px solid var(--vxw-border-strong); border-radius:9999px; padding:7px 13px; font-size:13px; font-weight:600; background:var(--vxw-surface); color:var(--vxw-text); transition:all .15s }
        .vx-clock .vx-clock-reason:hover { border-color:rgb(124 58 237 / .6) }
        .vx-clock .vx-clock-reason.is-on { background:#7c3aed; border-color:#7c3aed; color:#fff }
        .vx-clock .vx-clock-reason svg { height:16px; width:16px }
        .vx-clock .vx-clock-or { display:flex; align-items:center; gap:10px; margin:14px 0 10px; font-size:12px; color:var(--vxw-muted) }
        .vx-clock .vx-clock-or::before, .vx-clock .vx-clock-or::after { content:''; flex:1; height:1px; background:var(--vxw-border) }
        .vx-clock-modal { position:fixed; inset:0; z-index:60; display:flex; align-items:flex-start; justify-content:center; padding:6vh 16px 16px; background:rgb(3 7 18 / .5) }
        .vx-clock-modal-panel { display:flex; flex-direction:column; width:100%; max-width:560px; max-height:82vh; overflow:hidden; border-radius:16px; background:var(--vxw-surface); color:var(--vxw-text); box-shadow:0 24px 60px rgb(0 0 0 / .3) }
        .vx-clock-modal-list { overflow-y:auto; padding:4px 8px 12px }
        .vx-clock-day { position:sticky; top:0; z-index:1; padding:10px 8px 6px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--vxw-muted); background:var(--vxw-surface) }
        .vx-clock-show { display:flex; width:100%; align-items:center; gap:12px; border-radius:10px; padding:10px; text-align:left; transition:background .12s }
        .vx-clock-show:hover, .vx-clock-show:focus-visible { background:rgb(124 58 237 / .08); outline:none }
        .vx-clock-time { flex:none; width:68px; font-size:12.5px; font-weight:600; color:var(--vxw-muted); font-variant-numeric:tabular-nums }
        .vx-clock-badge { display:inline-flex; border-radius:9999px; padding:1px 8px; font-size:11px; font-weight:600; background:var(--vxw-sunken); color:var(--vxw-muted) }
    </style>

    <div class="vxw-card-body">
        <div class="flex flex-wrap items-center justify-between" style="gap:16px">
            <div class="vx-clock-status">
                <span @class(['vx-clock-dot', 'is-in' => $entry])><x-filament::icon icon="heroicon-o-clock" class="h-6 w-6" /></span>
                <div class="min-w-0">
                    <div class="vxw-eyebrow">Time clock</div>
                    @if ($entry)
                        <div class="vx-clock-big" x-data="{ s: {{ (int) $entry->clocked_in_at->diffInSeconds(now()) }} }" x-init="setInterval(() => s++, 1000)">
                            <span x-text="Math.floor(s/3600) + ':' + String(Math.floor(s%3600/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0')">{{ \App\Models\TimeEntry::formatMinutes((int) $entry->clocked_in_at->diffInMinutes(now())) }}</span>
                        </div>
                        <div class="vxw-meta">Clocked in at {{ $entry->clocked_in_at->copy()->setTimezone($tz)->format('g:i A') }} · {{ $entry->show_id ? ($entry->show?->title ?? 'Show #'.$entry->show_id) : ($entry->reason ? \App\Models\TimeEntry::REASONS[$entry->reason].($entry->notes ? ' — '.$entry->notes : '') : 'No show or reason yet') }}</div>
                    @else
                        <div class="vx-clock-big">Not clocked in</div>
                        <div class="vxw-meta">{{ $totals ? 'Clock in when you start working.' : 'Today: '.($today > 0 ? \App\Models\TimeEntry::formatMinutes($today) : 'no time yet') }}</div>
                    @endif
                </div>
                {{-- Your buddy: walks while you are on the clock, naps while you are off it. Click to pick another. --}}
                <div class="vx-buddy" x-data="{ choosing: false }" @click.outside="choosing = false" @keydown.escape="choosing = false">
                    <button type="button" @class(['vx-buddy-btn', 'is-walking' => $entry, 'is-napping' => ! $entry]) @click="choosing = ! choosing"
                            title="{{ \App\Support\Buddy::name($buddy) }} {{ $entry ? 'is on the clock with you' : 'is napping' }} — click to pick your buddy" aria-label="Pick your buddy">
                        <img src="/img/pokemon/{{ $buddy }}.gif" alt="">
                        @unless ($entry)<span class="vx-buddy-z" aria-hidden="true"><i>z</i><i>Z</i></span>@endunless
                    </button>
                    <div class="vx-buddy-pick" x-show="choosing" x-cloak x-transition.opacity>
                        <div class="vx-buddy-pick-title">Pick your buddy</div>
                        <div class="vx-buddy-grid">
                            @foreach (\App\Support\Buddy::CHOICES as $choice)
                                <button type="button" @class(['is-on' => $choice === $buddy]) wire:click="chooseBuddy('{{ $choice }}')" @click="choosing = false" title="{{ \App\Support\Buddy::name($choice) }}">
                                    <img src="/img/pokemon/{{ $choice }}.gif" alt="{{ \App\Support\Buddy::name($choice) }}" loading="lazy">
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @if ($totals)
                <div class="vx-clock-stats">
                    @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'total' => 'All time'] as $k => $l)
                        <div><div class="vxw-meta">{{ $l }}</div><div class="vx-clock-stat">{{ intdiv($totals[$k], 60) }}h {{ str_pad((string) ($totals[$k] % 60), 2, '0', STR_PAD_LEFT) }}m</div></div>
                    @endforeach
                </div>
            @endif
            <div class="flex flex-wrap gap-2">
                @if ($entry)
                    @unless ($changing)
                        <button type="button" wire:click="startChange" class="vxw-btn">{{ $entry->show_id || $entry->reason ? 'Change show or reason' : 'Add a show or reason' }}</button>
                    @endunless
                    <button type="button" wire:click="clockOut" wire:loading.attr="disabled" class="vxw-btn" style="background:#dc2626;color:#fff;border-color:#dc2626;min-height:44px;padding-inline:20px">
                        <x-filament::icon icon="heroicon-m-stop-circle" /> Clock out
                    </button>
                @else
                    @if ($compact)
                        <button type="button" class="vxw-btn" x-show="! more" @click="more = true"><x-filament::icon icon="heroicon-m-video-camera" /> Add a show or reason</button>
                    @endif
                    <button type="button" wire:click="clockIn" wire:loading.attr="disabled" class="vxw-btn" style="background:#059669;color:#fff;border-color:#059669;min-height:44px;padding-inline:22px">
                        <x-filament::icon icon="heroicon-m-play-circle" /> Clock in
                    </button>
                @endif
            </div>
        </div>

        @if (! $entry || $changing)
            <div class="vx-clock-section" x-show="more" x-cloak>
                <div class="vx-clock-label">What are you working on? <span style="text-transform:none;font-weight:500;letter-spacing:0">— optional, you can add it later</span></div>

                @if ($selected)
                    <div class="vx-clock-chosen">
                        <x-filament::icon icon="heroicon-o-video-camera" class="h-6 w-6 shrink-0" style="color:#7c3aed" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold">{{ $selected['title'] }}</div>
                            <div class="vxw-meta">{{ $selected['day'] }}@if($selected['time']) · {{ $selected['time'] }}@endif @if($selected['channel']) · {{ $selected['channel'] }}@endif</div>
                        </div>
                        <button type="button" @click="open()" class="vxw-btn vxw-btn--sm">Change</button>
                        <button type="button" wire:click="clearShow" class="vxw-btn vxw-btn--ghost vxw-btn--sm" aria-label="Remove show"><x-filament::icon icon="heroicon-m-x-mark" /></button>
                    </div>
                @else
                    <button type="button" class="vx-clock-pick" @click="open()">
                        <x-filament::icon icon="heroicon-o-video-camera" class="h-6 w-6 shrink-0" style="color:#7c3aed" />
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">Link one of {{ $isReviewer ? 'the' : 'your' }} shows</span>
                            <span class="vxw-meta block">{{ $mineCount ? $mineCount.' '.\Illuminate\Support\Str::plural('show', $mineCount).' of yours — recent and upcoming' : ($isReviewer ? 'Recent and upcoming shows' : 'No recent or upcoming shows assigned to you') }}</span>
                        </span>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" style="color:var(--vxw-muted)" />
                    </button>

                    <div class="vx-clock-or">or pick a reason</div>
                    <div class="vx-clock-reasons" role="group" aria-label="Reason">
                        @foreach ($reasons as $key => $label)
                            <button type="button" wire:click="pickReason('{{ $key }}')" @class(['vx-clock-reason', 'is-on' => $reason === $key]) aria-pressed="{{ $reason === $key ? 'true' : 'false' }}">
                                <x-filament::icon :icon="$reasonIcons[$key] ?? 'heroicon-m-tag'" /> {{ $label }}
                            </button>
                        @endforeach
                    </div>
                @endif

                @if (! $selected)
                    <label class="vxw-field mt-3">
                        <span class="sr-only">Note</span>
                        <input type="text" wire:model="note" maxlength="255" class="vxw-input" placeholder="{{ $reason === 'other' ? 'What are you working on?' : 'Add a note (optional)' }}">
                    </label>
                @endif

                @if ($changing)
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="saveLink" class="vxw-btn vxw-btn--primary vxw-btn--sm">Save</button>
                        <button type="button" wire:click="$set('changing', false)" class="vxw-btn vxw-btn--ghost vxw-btn--sm">Cancel</button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Show picker --}}
    <template x-teleport="body">
        <div class="vx-clock-modal" x-show="picker" x-cloak x-transition.opacity @click.self="picker = false" role="dialog" aria-modal="true" aria-label="Choose a show">
            <div class="vx-clock-modal-panel">
                <div class="flex items-center justify-between gap-3 px-5 pt-5">
                    <div class="text-lg font-bold">Choose a show</div>
                    <button type="button" class="vxw-btn vxw-btn--ghost vxw-btn--sm" @click="picker = false" aria-label="Close"><x-filament::icon icon="heroicon-m-x-mark" /></button>
                </div>
                <div class="px-5 pt-3">
                    <div class="relative">
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute h-4 w-4" style="left:12px;top:50%;transform:translateY(-50%);color:var(--vxw-muted)" />
                        <input x-ref="search" x-model="q" type="search" class="vxw-input" style="padding-left:36px" placeholder="Search by title, channel or day">
                    </div>
                    @if ($isReviewer)
                        <div class="vxw-chips mt-3" role="tablist">
                            <button type="button" role="tab" class="vxw-chip" :class="tab === 'mine' && 'is-active'" @click="tab = 'mine'">My shows <span class="vxw-chip-count">{{ $mineCount }}</span></button>
                            <button type="button" role="tab" class="vxw-chip" :class="tab === 'all' && 'is-active'" @click="tab = 'all'">All shows <span class="vxw-chip-count">{{ count($picker) }}</span></button>
                        </div>
                    @endif
                </div>
                <div class="vx-clock-modal-list mt-2">
                    <template x-for="g in groups" :key="g.key">
                        <div>
                            <div class="vx-clock-day" x-text="g.day"></div>
                            <template x-for="s in g.items" :key="s.id">
                                <button type="button" class="vx-clock-show" @click="choose(s.id)">
                                    <span class="vx-clock-time" x-text="s.time || '—'"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold" x-text="s.title"></span>
                                        <span class="mt-0.5 flex flex-wrap gap-1.5">
                                            <span class="vx-clock-badge" x-show="s.channel" x-text="s.channel"></span>
                                            <span class="vx-clock-badge" style="background:rgb(124 58 237 / .12);color:#7c3aed" x-show="tab === 'all' && s.mine">Your show</span>
                                        </span>
                                    </span>
                                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0" style="color:var(--vxw-muted)" />
                                </button>
                            </template>
                        </div>
                    </template>
                    <div x-show="list.length === 0" class="px-4 py-10 text-center">
                        <div class="font-semibold">No shows match</div>
                        <div class="vxw-meta mt-1" x-text="tab === 'mine' ? 'Your assigned upcoming shows and shows from the last two weeks are listed. Pick a reason instead, or link the show later.' : 'Try a different search.'"></div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</section>
