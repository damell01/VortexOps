<x-filament-panels::page>
@php
    $businesses = $this->businesses();
    $columns = $this->columns();
    $selectedCount = count($selected);
    $current = \App\Support\BusinessContext::current();
@endphp
<div class="vxb" x-data="{
        dragging: false,
        over: null,
        start(ev, id) {
            const sel = ($wire.selected || []).map(Number);
            const ids = sel.includes(Number(id)) ? sel : [Number(id)];
            ev.dataTransfer.effectAllowed = 'move';
            ev.dataTransfer.setData('text/plain', JSON.stringify(ids));
            this.dragging = true;
        },
        drop(ev, businessId) {
            this.over = null; this.dragging = false;
            let ids = [];
            try { ids = JSON.parse(ev.dataTransfer.getData('text/plain') || '[]'); } catch (e) {}
            if (ids.length) $wire.assign(ids, businessId);
        },
    }" @dragend="dragging = false; over = null">

    <div class="vxb-toolbar">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search people…" class="vxb-search" aria-label="Search people">
        <div class="vxb-seg" role="tablist">
            @foreach(['all' => 'Everyone', 'streamer' => 'Streamers', 'fulfillment' => 'Fulfillment'] as $key => $label)
                <button type="button" wire:click="$set('memberType', '{{ $key }}')" @class(['is-on' => $memberType === $key])>{{ $label }}</button>
            @endforeach
        </div>
        <label class="vxb-check"><input type="checkbox" wire:model.live="showInactive"> Show inactive</label>
        <button type="button" class="vxb-btn" wire:click="newBusiness">+ Add business</button>
    </div>

    @if($editingBusinessId !== null)
        <form wire:submit="saveBusiness" class="vxb-editor">
            <strong>{{ $editingBusinessId ? 'Edit business' : 'New business' }}</strong>
            <label>Name <input type="text" wire:model="businessForm.name" required maxlength="120"></label>
            <label>Short name <input type="text" wire:model="businessForm.short_name" maxlength="20" placeholder="e.g. VCX"></label>
            <label>Colour <input type="color" wire:model="businessForm.color"></label>
            <label class="vxb-check"><input type="checkbox" wire:model="businessForm.tips_monthly"> Pay tips monthly<x-vx-help topic="business.tips_monthly" /> <small>Tips come off weekly pay onto their own line, paid by a Tips pay run.</small></label>
            @error('businessForm.name')<span class="vxb-err">{{ $message }}</span>@enderror
            @error('businessForm.color')<span class="vxb-err">{{ $message }}</span>@enderror
            <div class="vxb-editor-actions">
                <button type="submit" class="vxb-btn vxb-btn--primary">Save</button>
                <button type="button" class="vxb-btn" wire:click="cancelBusiness">Cancel</button>
                @if($editingBusinessId)
                    <button type="button" class="vxb-btn vxb-btn--danger" wire:click="deleteBusiness({{ $editingBusinessId }})" wire:confirm="Delete this business? Only an empty business with no Pay Runs can be deleted.">Delete</button>
                @endif
            </div>
        </form>
    @endif

    <div @class(['vxb-bulk', 'is-on' => $selectedCount > 0])>
        @if($selectedCount > 0)
            <span><strong>{{ $selectedCount }}</strong> selected — move to</span>
            @foreach($businesses as $business)
                <button type="button" class="vxb-btn vxb-btn--biz" style="--c: {{ $business->color }}" wire:click="moveSelected({{ $business->id }})">{{ $business->name }}</button>
            @endforeach
            <button type="button" class="vxb-btn" wire:click="moveSelected(null)">Not assigned</button>
            <button type="button" class="vxb-link" wire:click="clearSelection">Clear</button>
        @else
            <span class="vxb-muted">Tip: drag a card onto a column. Tick several people to drag or move them together.</span>
        @endif
    </div>

    <div class="vxb-board" style="--cols: {{ count($columns) }}">
        @foreach($columns as $key => $members)
            @php
                $business = $key === '' ? null : $businesses->firstWhere('id', (int) $key);
                $color = $business?->color ?? '#9ca3af';
                $target = $business ? $business->id : 'null';
            @endphp
            <section class="vxb-col" style="--c: {{ $color }}"
                :class="over === '{{ $key }}' && 'is-over'"
                @dragover.prevent="over = '{{ $key }}'"
                @dragleave.self="over = null"
                @drop.prevent="drop($event, {{ $target }})">
                <header class="vxb-col-head">
                    <span class="vxb-dot" aria-hidden="true"></span>
                    <div class="vxb-col-title">
                        <h3>{{ $business?->name ?? 'Not assigned' }}</h3>
                        <span>{{ $members->count() }} {{ \Illuminate\Support\Str::plural('person', $members->count()) }}@if($business && $current?->id === $business->id) · viewing @endif</span>
                    </div>
                    @if($members->isNotEmpty())
                        <button type="button" class="vxb-link" wire:click="selectColumn(@js($members->pluck('id')->all()))">Select all</button>
                    @endif
                    @if($business)
                        <button type="button" class="vxb-link" wire:click="mountAction('setUpInventoryForBusiness', { business: {{ $business->id }} })" title="Give everyone in {{ $business->name }} their own inventory location">Set up inventory</button>
                        <button type="button" class="vxb-link" wire:click="mountAction('businessStatement', { business: {{ $business->id }} })" title="Monthly statement PDF for {{ $business->name }}">Statement</button>
                        <button type="button" class="vxb-link" wire:click="editBusiness({{ $business->id }})" title="Rename or recolour">Edit</button>
                    @endif
                </header>
                @if(! $business)
                    <p class="vxb-note">Not in any business yet. Their pay won't go into a business's Pay Run until they're moved.</p>
                @endif
                <ul class="vxb-list">
                    @forelse($members as $member)
                        <li wire:key="m-{{ $member->id }}" draggable="true" @dragstart="start($event, {{ $member->id }})"
                            @class(['vxb-card', 'is-selected' => in_array($member->id, array_map('intval', $selected), true), 'is-inactive' => $member->status !== 'active'])>
                            <input type="checkbox" value="{{ $member->id }}" wire:model.live="selected" aria-label="Select {{ $member->name }}">
                            <div class="vxb-card-main">
                                <div class="vxb-name">{{ $member->name }}</div>
                                <div class="vxb-meta">
                                    <span class="vxb-tag">{{ $member->isFulfillment() ? 'Fulfillment' : ($member->isRemote() ? 'Remote' : 'In-house') }}</span>
                                    @if($member->channel)<span class="vxb-tag vxb-tag--soft">{{ $member->channel->name }}</span>@endif
                                    @if($member->status !== 'active')<span class="vxb-tag vxb-tag--warn">{{ \App\Models\Streamer::statusLabels()[$member->status] ?? $member->status }}</span>@endif
                                </div>
                                <div class="vxb-pay">{{ $this->payLabel($member) }}</div>
                            </div>
                            <span class="vxb-grip" aria-hidden="true">⋮⋮</span>
                        </li>
                    @empty
                        <li class="vxb-empty">Drop people here</li>
                    @endforelse
                </ul>
            </section>
        @endforeach
    </div>
</div>

<style>
.vxb{display:grid;gap:14px}
.vxb-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.vxb-search{flex:1 1 220px;max-width:340px;border-radius:10px;border:1px solid var(--vx-border,#e5e7eb);padding:8px 12px;font-size:14px;background:#fff}
.dark .vxb-search{background:#111827;color:#e5e7eb;border-color:#374151}
.vxb-seg{display:inline-flex;border:1px solid var(--vx-border,#e5e7eb);border-radius:10px;overflow:hidden}
.vxb-seg button{padding:7px 12px;font-size:13px;font-weight:600;color:#6b7280}
.vxb-seg button.is-on{background:var(--primary-600,#7c3aed);color:#fff}
.vxb-check{display:inline-flex;gap:6px;align-items:center;font-size:13px;color:#6b7280}
.vxb-btn{border:1px solid var(--vx-border,#e5e7eb);border-radius:9px;padding:6px 12px;font-size:13px;font-weight:700;background:#fff;color:#1f2937}
.dark .vxb-btn{background:#1f2937;color:#e5e7eb;border-color:#374151}
.vxb-btn--primary{background:var(--primary-600,#7c3aed);border-color:transparent;color:#fff}
.vxb-btn--danger{color:#b91c1c;border-color:#fecaca}
.vxb-btn--biz{border-color:var(--c);color:var(--c);background:color-mix(in srgb,var(--c) 8%,#fff)}
.dark .vxb-btn--biz{background:color-mix(in srgb,var(--c) 20%,#111827);color:color-mix(in srgb,var(--c) 55%,#fff)}
.vxb-link{font-size:12px;font-weight:700;color:var(--primary-600,#7c3aed);text-decoration:underline;text-underline-offset:2px}
.vxb-toolbar .vxb-btn:last-child{margin-left:auto}
.vxb-editor{display:flex;flex-wrap:wrap;gap:12px;align-items:end;padding:14px;border-radius:12px;border:1px solid var(--vx-border,#e5e7eb);background:#fafafa}
.dark .vxb-editor{background:#111827;border-color:#374151}
.vxb-editor label{display:grid;gap:4px;font-size:12px;font-weight:600;color:#6b7280}
.vxb-editor label.vxb-check{display:flex;align-items:center;gap:6px;flex-wrap:wrap;max-width:22rem}.vxb-check small{flex-basis:100%;font-weight:400}
.vxb-editor input[type=text]{border-radius:8px;border:1px solid var(--vx-border,#e5e7eb);padding:6px 10px;font-size:14px}
.vxb-editor input[type=color]{width:52px;height:34px;border-radius:8px}
.vxb-editor-actions{display:flex;gap:8px}
.vxb-err{color:#b91c1c;font-size:12px}
.vxb-bulk{display:flex;flex-wrap:wrap;gap:8px;align-items:center;min-height:42px;padding:8px 12px;border-radius:12px;border:1px dashed var(--vx-border,#e5e7eb);font-size:13px}
.vxb-bulk.is-on{border-style:solid;border-color:var(--primary-500,#8b5cf6);background:color-mix(in srgb,var(--primary-500,#8b5cf6) 7%,transparent);position:sticky;top:76px;z-index:5;backdrop-filter:blur(6px)}
.vxb-muted{color:#6b7280}
.vxb-board{display:grid;grid-template-columns:repeat(var(--cols),minmax(260px,1fr));gap:14px;overflow-x:auto;padding-bottom:6px}
.vxb-col{display:flex;flex-direction:column;min-height:240px;border-radius:14px;border:1px solid var(--vx-border,#e5e7eb);border-top:4px solid var(--c);background:color-mix(in srgb,var(--c) 4%,#fff);transition:box-shadow .15s,background .15s}
.dark .vxb-col{background:color-mix(in srgb,var(--c) 8%,#0b1120);border-color:#1f2937;border-top-color:var(--c)}
.vxb-col.is-over{box-shadow:0 0 0 3px color-mix(in srgb,var(--c) 45%,transparent);background:color-mix(in srgb,var(--c) 12%,#fff)}
.vxb-col-head{display:flex;align-items:center;gap:10px;padding:12px 14px 8px}
.vxb-dot{width:12px;height:12px;border-radius:999px;background:var(--c);flex:none}
.vxb-col-title{flex:1;min-width:0}
.vxb-col-title h3{font-size:15px;font-weight:800;line-height:1.2}
.vxb-col-title span{font-size:12px;color:#6b7280}
.vxb-note{margin:0 14px 6px;font-size:12px;color:#6b7280}
.vxb-list{display:grid;gap:8px;padding:6px 10px 14px;max-height:68vh;overflow-y:auto}
.vxb-card{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:10px;background:#fff;border:1px solid var(--vx-border,#e5e7eb);cursor:grab;box-shadow:0 1px 2px rgba(0,0,0,.04)}
.dark .vxb-card{background:#111827;border-color:#1f2937}
.vxb-card:active{cursor:grabbing}
.vxb-card.is-selected{border-color:var(--primary-500,#8b5cf6);box-shadow:0 0 0 2px color-mix(in srgb,var(--primary-500,#8b5cf6) 30%,transparent)}
.vxb-card.is-inactive{opacity:.6}
.vxb-card input[type=checkbox]{margin-top:3px;border-radius:5px}
.vxb-card-main{flex:1;min-width:0}
.vxb-name{font-weight:700;font-size:14px}
.vxb-meta{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px}
.vxb-tag{font-size:11px;font-weight:700;padding:1px 7px;border-radius:999px;background:#f3f4f6;color:#374151}
.dark .vxb-tag{background:#1f2937;color:#d1d5db}
.vxb-tag--soft{background:transparent;border:1px solid var(--vx-border,#e5e7eb)}
.vxb-tag--warn{background:#fef3c7;color:#92400e}
.vxb-pay{margin-top:5px;font-size:12px;color:#6b7280}
.vxb-grip{color:#9ca3af;font-size:14px;line-height:1;letter-spacing:-2px}
.vxb-empty{padding:22px 10px;text-align:center;font-size:13px;color:#9ca3af;border:2px dashed var(--vx-border,#e5e7eb);border-radius:10px;list-style:none}
@media (max-width:767px){.vxb-board{grid-template-columns:1fr}.vxb-toolbar .vxb-btn:last-child{margin-left:0}}
</style>
</x-filament-panels::page>
