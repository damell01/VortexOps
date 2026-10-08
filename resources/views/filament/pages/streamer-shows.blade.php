@php
    $groups = $this->groups();
    $summary = $this->quickSummary();
    $allShows = collect($groups['needs_you'])->map(fn ($s) => $s + ['bucket' => 'needs_you'])
        ->concat(collect($groups['upcoming'])->map(fn ($s) => $s + ['bucket' => 'upcoming']))
        ->concat(collect($groups['waiting'])->map(fn ($s) => $s + ['bucket' => 'waiting']))
        ->concat(collect($groups['done'])->map(fn ($s) => $s + ['bucket' => 'done']))
        ->values();

    $tones = ['danger' => 'bad', 'warning' => 'warn', 'info' => 'info', 'success' => 'ok', 'gray' => ''];
    $nextUp = $groups['needs_you'][0] ?? null;
    $defaultTab = $summary['needs_you'] > 0 ? 'needs_you' : ($summary['upcoming'] > 0 ? 'upcoming' : 'all');
    $calendarUrl = \App\Filament\Pages\Shows::canAccess() ? \App\Filament\Pages\Shows::getUrl() : null;
    $tabs = [
        ['needs_you', 'Waiting on you', $summary['needs_you']],
        ['upcoming', 'Upcoming', $summary['upcoming']],
        ['waiting', 'Submitted', $summary['submitted']],
        ['done', 'Approved', $summary['approved']],
        ['all', 'All', $allShows->count()],
    ];
@endphp

<x-filament-panels::page>
    <div class="vxw" data-vx-page="streamer-shows" x-data="{ tab: @js($defaultTab), sort: 'newest' }">
        {{-- What needs doing, in one line, with the one button that does it. --}}
        <section class="vxw-card">
            <div class="vxw-card-body">
                <div class="vxw-hero">
                    <div>
                        <div class="vxw-eyebrow">Your shows</div>
                        @if($summary['needs_you'] > 0)
                            <h2 class="vxw-h1">{{ $summary['needs_you'] }} {{ \Illuminate\Support\Str::plural('show', $summary['needs_you']) }} waiting on you</h2>
                            <p class="vxw-sub">Log the items you used so your report can be approved and paid.</p>
                        @else
                            <h2 class="vxw-h1">You're all caught up</h2>
                            <p class="vxw-sub">Every show you've streamed has a report. New shows appear here after they air.</p>
                        @endif
                    </div>
                    <div class="vxw-hero-actions">
                        @if($calendarUrl)
                            <a class="vxw-btn" href="{{ $calendarUrl }}">
                                <x-filament::icon icon="heroicon-m-calendar-days" /> Calendar
                            </a>
                        @endif
                        @if($nextUp && $nextUp['url'])
                            <a class="vxw-btn vxw-btn--primary" href="{{ $nextUp['url'] }}">
                                Log next show
                                <x-filament::icon icon="heroicon-m-arrow-right" />
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="vxw-chips" role="tablist" aria-label="Filter your shows">
                @foreach($tabs as [$key, $label, $count])
                    <button type="button" role="tab" class="vxw-chip" :class="tab==='{{ $key }}' && 'is-active'" :aria-selected="tab==='{{ $key }}'" @click="tab='{{ $key }}'">
                        {{ $label }} <span class="vxw-chip-count">{{ $count }}</span>
                    </button>
                @endforeach
            </div>
            <label class="flex items-center gap-2">
                <span class="sr-only">Sort shows</span>
                <select x-model="sort" class="vxw-select" style="min-height:36px;width:auto;padding-block:0">
                    <option value="newest">Newest first</option>
                    <option value="oldest">Oldest first</option>
                </select>
            </label>
        </div>

        <section id="show-grid" class="vxw-tiles" aria-live="polite">
            @foreach ($allShows as $show)
                <article wire:key="streamer-show-{{ $show['id'] }}"
                    x-show="tab==='all' || tab==='{{ $show['bucket'] }}'" x-cloak
                    :style="{ order: sort==='newest' ? {{ -$show['day'] }} : {{ $show['day'] }} }"
                    @class(['vxw-tile', 'vxw-tile--attn' => $show['bucket'] === 'needs_you'])
                    style="cursor:default">
                    <div class="flex items-start justify-between gap-3">
                        <span class="vxw-pill vxw-pill--dot {{ ($tones[$show['tone']] ?? '') ? 'vxw-pill--' . $tones[$show['tone']] : '' }}">{{ $show['state'] }}</span>
                        <span class="vxw-meta whitespace-nowrap">
                            {{ $show['date'] }}@if($show['time']) · {{ $show['time']->format('g:i A') }}@endif
                        </span>
                    </div>
                    <div>
                        <h3 class="vxw-tile-title">{{ $show['title'] }}</h3>
                        @if($show['channel'])<div class="vxw-meta mt-1">{{ $show['channel'] }}</div>@endif
                    </div>

                    @if($show['revision_requested'] && $show['revision_reason'])
                        <div class="vxw-alert vxw-alert--info" style="padding:8px 10px;font-size:12.5px">
                            <x-filament::icon icon="heroicon-m-chat-bubble-left-ellipsis" />
                            <span>You asked: “{{ $show['revision_reason'] }}”</span>
                        </div>
                    @endif

                    <div class="vxw-tile-foot">
                        @if($show['url'])
                            <a href="{{ $show['url'] }}" class="vxw-btn vxw-btn--block {{ $show['bucket'] === 'needs_you' ? 'vxw-btn--primary' : '' }}">
                                {{ $show['action'] }} <x-filament::icon icon="heroicon-m-arrow-right" />
                            </a>
                        @else
                            <span class="vxw-meta">Report opens after the show airs.</span>
                        @endif
                    </div>

                    @if($show['can_request_revision'] && $revisionFor !== $show['id'])
                        <button type="button" wire:click="askForChanges({{ $show['id'] }})" class="vxw-btn vxw-btn--ghost vxw-btn--sm -mt-1 self-center">Need to change something?</button>
                    @endif
                    @if($revisionFor === $show['id'])
                        <div class="vxw-field rounded-[var(--vxw-radius-sm)] border border-[var(--vxw-border)] bg-[var(--vxw-surface-2)] p-3">
                            <label class="vxw-label" for="revision-{{ $show['id'] }}">What needs changing?</label>
                            <textarea id="revision-{{ $show['id'] }}" wire:model="revisionReason" rows="2" class="vxw-textarea" style="min-height:72px" placeholder="e.g. I logged the wrong item"></textarea>
                            <div class="flex gap-2">
                                <button type="button" wire:click="submitRevisionRequest" wire:loading.attr="disabled" wire:target="submitRevisionRequest" class="vxw-btn vxw-btn--primary vxw-btn--sm">Request reopen</button>
                                <button type="button" wire:click="cancelRevisionRequest" class="vxw-btn vxw-btn--ghost vxw-btn--sm">Cancel</button>
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach

            @foreach($tabs as [$key, $label, $count])
                @if($count === 0 && $key !== 'all')
                    <div class="vxw-card col-span-full" x-show="tab==='{{ $key }}'" x-cloak>
                        <div class="vxw-empty">
                            <x-filament::icon icon="{{ $key === 'needs_you' ? 'heroicon-o-check-circle' : 'heroicon-o-calendar' }}" />
                            <div class="vxw-empty-title">{{ $key === 'needs_you' ? 'Nothing waiting on you' : 'No ' . strtolower($label) . ' shows' }}</div>
                            @if($calendarUrl)<div class="vxw-empty-text">See every past and upcoming show on the <a class="vxw-link" href="{{ $calendarUrl }}">calendar</a>.</div>@endif
                        </div>
                    </div>
                @endif
            @endforeach
        </section>

        @if ($allShows->isEmpty())
            <section class="vxw-card">
                <div class="vxw-empty">
                    <x-filament::icon icon="heroicon-o-video-camera" />
                    <div class="vxw-empty-title">No shows yet</div>
                    <div class="vxw-empty-text">Once you're assigned to a show it will appear here.</div>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
