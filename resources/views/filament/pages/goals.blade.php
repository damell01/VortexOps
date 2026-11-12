<x-filament-panels::page>
<x-reports.shell>
<div class="vxw" data-vx-page="goals">
    <section class="vxw-card">
        <div class="vxw-card-body" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between">
            <div style="display:flex;gap:8px;align-items:center">
                <button type="button" class="vxw-btn vxw-btn--sm" wire:click="shiftMonth(-1)" aria-label="Previous month">‹</button>
                <strong style="min-width:130px;text-align:center">{{ $monthLabel }}</strong>
                <button type="button" class="vxw-btn vxw-btn--sm" wire:click="shiftMonth(1)" aria-label="Next month">›</button>
            </div>
            @if ($hasPrevious)
                <button type="button" class="vxw-btn vxw-btn--sm" wire:click="copyPrevious">Copy last month's goals</button>
            @endif
        </div>
        <div class="vxw-card-body" style="border-top:1px solid rgba(148,163,184,.2)">
            @if ($progress->isEmpty())
                <div style="padding:12px;text-align:center">
                    <p style="margin:0 0 6px;font-weight:600">No goals for {{ $monthLabel }} yet</p>
                    <p class="vxw-meta" style="margin:0">Add one below{{ $hasPrevious ? ', or copy last month\'s' : '' }}. They show on the dashboard with a progress bar.</p>
                </div>
            @else
                @include('filament.components.goal-bars', ['progress' => $progress, 'removable' => true])
                @if ($isCurrent)<p class="vxw-meta" style="margin:12px 0 0">The thin line marks where a steady pace would be today.</p>@endif
            @endif
        </div>
    </section>

    <section class="vxw-card" style="margin-top:16px">
        <form class="vxw-card-body" wire:submit="save" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
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
            <button type="submit" class="vxw-btn vxw-btn--primary vxw-btn--sm">Save goal</button>
        </form>
    </section>
</div>
</x-reports.shell>
</x-filament-panels::page>
