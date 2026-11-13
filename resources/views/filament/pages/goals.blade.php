<x-filament-panels::page>
<x-reports.shell>
<div class="vxr" data-vx-page="goals">
    <div class="vxr-head">
        <div><h1 class="vxr-title">Goals</h1><p class="vxr-subtitle">Monthly targets for everyone, a business or a channel. They show on the dashboard and on the reports' charts.</p></div>
        <div class="vxr-actions">
            <div class="vxr-seg"><button type="button" wire:click="shiftMonth(-1)" aria-label="Previous month">‹</button><button type="button" class="is-active" style="min-width:140px;justify-content:center">{{ $monthLabel }}</button><button type="button" wire:click="shiftMonth(1)" aria-label="Next month">›</button></div>
            @if ($hasPrevious)<button type="button" class="vxr-btn vxr-btn--outline" wire:click="copyPrevious">Copy last month's goals</button>@endif
        </div>
    </div>

    @if ($progress->isEmpty())
        <section class="vxr-card"><div class="vxr-card-body"><div class="vxr-empty"><b>No goals for {{ $monthLabel }} yet</b>Add one below{{ $hasPrevious ? ', or copy last month\'s' : '' }}.</div></div></section>
    @else
        @php $tones = ['done' => ['ok', 'Goal hit', 'var(--vxw-ok)'], 'on_track' => ['ok', 'On track', 'var(--vxw-accent)'], 'behind' => ['warn', 'Behind', 'var(--vxw-warn, #b45309)'], 'missed' => ['bad', 'Missed', 'var(--vxw-bad)']]; $C = 2 * M_PI * 34; @endphp
        <div class="vxgl">
            @foreach ($progress as $g)
                @php
                    [$chip, $word, $col] = $tones[$g['status']];
                    $fmt = fn ($v) => $g['money'] ? '$'.number_format($v, 0) : number_format($v);
                    $proj = $isCurrent && $g['expected'] > 0 ? $g['actual'] / ($g['expected'] / 100) : null;
                @endphp
                <section class="vxr-card vxgl-card" wire:key="goal-{{ $g['id'] }}">
                    <svg viewBox="0 0 80 80" class="vxgl-ring" aria-hidden="true">
                        <circle cx="40" cy="40" r="34" fill="none" stroke="var(--vxw-sunken)" stroke-width="8" />
                        <circle cx="40" cy="40" r="34" fill="none" stroke="{{ $col }}" stroke-width="8" stroke-linecap="round" stroke-dasharray="{{ round(min(100, $g['pct']) / 100 * $C, 1) }} {{ round($C, 1) }}" transform="rotate(-90 40 40)" class="vxgl-arc" />
                        <text x="40" y="45" text-anchor="middle">{{ round($g['pct']) }}%</text>
                    </svg>
                    <div style="min-width:0">
                        <div class="vxgl-top"><b>{{ $g['label'] }}</b><span class="vxr-chip {{ $chip }}">{{ $word }}</span></div>
                        <div class="vxgl-scope">{{ $g['scope'] }}</div>
                        <div class="vxgl-num">{{ $fmt($g['actual']) }} <span>of {{ $fmt($g['target']) }}</span></div>
                        @if ($proj !== null)<div class="vxgl-scope">On pace for <b style="color:var(--vxw-text)">{{ $fmt($proj) }}</b> · {{ $proj >= $g['target'] ? 'beats the goal' : $fmt($g['target'] - $proj).' short' }}</div>@endif
                    </div>
                    <button type="button" class="vxgl-rm" wire:click="remove({{ $g['id'] }})" wire:confirm="Remove this goal?" aria-label="Remove goal">×</button>
                </section>
            @endforeach
        </div>
    @endif

    <section class="vxr-card">
        <div class="vxr-card-head"><div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-plus-circle" /> Add or change a goal</h2><p class="vxr-card-sub">Saving a goal that already exists for that month replaces its target.</p></div></div>
        <form class="vxr-card-body" wire:submit="save" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
            <label class="vxw-meta" for="g-metric" style="display:grid;gap:4px">Goal
                <select id="g-metric" class="vxw-input" wire:model="metric">@foreach(\App\Models\Goal::METRICS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            </label>
            <label class="vxw-meta" for="g-scope" style="display:grid;gap:4px">For
                <select id="g-scope" class="vxw-input" wire:model.live="scope">@foreach(\App\Models\Goal::SCOPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            </label>
            @if ($scope !== 'all')
                <label class="vxw-meta" for="g-scope-id" style="display:grid;gap:4px">{{ $scope === 'channel' ? 'Channel' : 'Business' }}
                    <select id="g-scope-id" class="vxw-input" wire:model="scopeId"><option value="">Choose…</option>@foreach(($scope === 'channel' ? $channels : $businesses) as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select>
                    @error('scopeId')<span style="color:#dc2626">{{ $message }}</span>@enderror
                </label>
            @endif
            <label class="vxw-meta" for="g-target" style="display:grid;gap:4px">Target for {{ $monthLabel }}
                <input id="g-target" class="vxw-input" type="text" inputmode="decimal" wire:model="target" placeholder="{{ in_array($metric, ['whatnot_net', 'gross']) ? '$20,000' : '40' }}" style="width:140px">
                @error('target')<span style="color:#dc2626">{{ $message }}</span>@enderror
            </label>
            <button type="submit" class="vxr-btn vxr-btn--primary">Save goal</button>
        </form>
    </section>
</div>
</x-reports.shell>
</x-filament-panels::page>
