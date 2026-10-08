<x-filament-panels::page>
<div class="vx-fulfill-preview">
    <div class="vx-fp-intro">
        <div><span class="vx-fp-tag">Preview · sample data</span><h2>{{ $this->isAdminView() ? 'Fulfillment control desk' : 'My fulfillment work' }}</h2><p>{{ $this->isAdminView() ? 'See every channel, assign the team, and clear blockers.' : 'Your assigned shows, picking locations, and next steps in one place.' }}</p></div>
        <div class="vx-fp-switch">
            @if(auth()->user()?->isAdmin())
            <button type="button" wire:click="setMode('admin')" @class(['active'=>$this->isAdminView()])>Admin view</button>
            <button type="button" wire:click="setMode('staff')" @class(['active'=>!$this->isAdminView()])>Staff view</button>
            @endif
            <button type="button" wire:click="resetDemo">Reset preview</button>
        </div>
    </div>
    <div class="vx-fp-kpis">
        @foreach(['ready'=>'Ready to pick','picking'=>'Picking','packing'=>'Packing','blocked'=>'Needs attention'] as $stage=>$label)
        <button type="button" wire:click="$set('filter', '{{ $stage }}')" @class(['vx-fp-kpi','active'=>$filter===$stage])><span>{{ $label }}</span><strong>{{ collect($this->work())->where('stage',$stage)->count() }}</strong><small>{{ $stage==='blocked' ? 'Resolve before packing' : 'View work queue' }}</small></button>
        @endforeach
    </div>
    <section class="vx-fp-panel">
        <div class="vx-fp-toolbar"><div><h3>{{ $this->isAdminView() ? 'Show work queue' : 'Assigned to you' }}</h3><p>{{ $this->isAdminView() ? 'Approved report → pick → pack → complete' : 'Sample worker: Avery · two assigned shows' }}</p></div><label><span class="sr-only">Search fulfillment work</span><input wire:model.live.debounce.350ms="query" placeholder="Search show, channel or streamer…"></label></div>
        <div class="vx-fp-tabs">@foreach(['all'=>'All work','ready'=>'Ready','picking'=>'Picking','packing'=>'Packing','blocked'=>'Blocked','complete'=>'Complete'] as $key=>$label)<button type="button" wire:click="$set('filter','{{ $key }}')" @class(['active'=>$filter===$key])>{{ $label }}</button>@endforeach</div>
        <div class="vx-fp-work">
            @forelse($this->filteredWork() as $row)
            <article wire:key="fulfill-demo-{{ $row['id'] }}" class="vx-fp-work-card">
                <div class="vx-fp-card-head"><span class="vx-fp-channel">{{ $row['channel'] }}</span><span @class(['vx-fp-status','is-blocked'=>$row['stage']==='blocked'])>{{ ucfirst($row['stage']) }}</span></div>
                <h3>{{ $row['title'] }}</h3><p>{{ $row['host'] }} · {{ $row['priority'] }}</p>
                <div class="vx-fp-numbers"><div><strong>{{ $row['packages'] }}</strong><span>Packages</span></div><div><strong>{{ $row['items'] }}</strong><span>Items to pick</span></div></div>
                <div class="vx-fp-card-meta"><span>{{ $row['location'] }}</span><span>Assigned: {{ $row['assigned'] }}</span></div>
                @if($this->isAdminView())
                <label class="vx-fp-assignment">Assign worker<select aria-label="Worker for {{ $row['title'] }}" wire:change="assignDemo({{ $row['id'] }}, $event.target.value)">@foreach(['Unassigned','Avery','Morgan'] as $name)<option value="{{ $name }}" @selected($row['assigned']===$name)>{{ $name }}</option>@endforeach</select></label>
                @endif
                <button type="button" class="vx-fp-primary" wire:click="openWork({{ $row['id'] }})">{{ $row['stage']==='blocked' ? 'Review stock exception' : 'Open work' }} <span aria-hidden="true">→</span></button>
            </article>
            @empty
            <div class="vx-fp-empty"><h3>No work in this view</h3><p>Try another status or clear your search.</p><button type="button" wire:click="$set('filter','all')">Show all work</button></div>
            @endforelse
        </div>
    </section>
    @if($this->isAdminView())
    <section class="vx-fp-panel vx-fp-team"><div><h3>Team workload</h3><p>Balance assignments before the next picking session.</p></div>@foreach(['Avery','Morgan','Unassigned'] as $name)<div><strong>{{ $name }}</strong><span>{{ collect($this->work())->where('assigned',$name)->where('stage','!=','complete')->count() }} active shows</span></div>@endforeach</section>
    @endif
    <p class="vx-fp-note">Interactive mockup. Sample actions only; live inventory, shipment and payroll records are unaffected.</p>

    @if($row=$this->selectedWork())
    <div class="vx-fp-overlay" x-data x-init="$nextTick(() => $refs.close.focus())" @keydown.escape.window="$wire.set('opened',null)">
        <button type="button" class="vx-fp-backdrop" aria-label="Close work details" wire:click="$set('opened', null)"></button>
        <aside role="dialog" aria-modal="true" aria-labelledby="vx-work-heading" class="vx-fp-drawer" x-trap.inert.noscroll="true">
            <div class="vx-fp-drawer-head"><span class="vx-fp-tag">{{ ucfirst($row['stage']) }} · sample work</span><button type="button" x-ref="close" wire:click="$set('opened', null)" aria-label="Close work details">✕</button></div>
            <h2 id="vx-work-heading">{{ $row['title'] }}</h2><p>{{ $row['channel'] }} · {{ $row['host'] }}</p>
            <div class="vx-fp-steps">@foreach(['ready'=>'Ready','picking'=>'Pick','packing'=>'Pack','complete'=>'Complete'] as $key=>$label)<span @class(['active'=>$row['stage']===$key])>{{ $label }}</span>@endforeach</div>
            @if($row['stage']==='blocked')<div class="vx-fp-exception"><strong>Stock needs review</strong><p>Example: 2 booster boxes are missing from A-08. Check receiving or request a stock correction before continuing.</p></div>@endif
            <h3>Picking list</h3><p class="vx-fp-note">Sample line items · {{ $row['location'] }}</p>
            <div class="vx-fp-pick-list">@foreach([['Booster box',4,'Shelf A-04'],['Sleeved pack',12,'Shelf B-12'],['Promo card',8,'Shelf C-03']] as [$item,$quantity,$location])<label><input type="checkbox"><span><strong>{{ $item }}</strong><small>{{ $location }}</small></span><b>×{{ $quantity }}</b></label>@endforeach</div>
            <h3>Package checklist</h3><div class="vx-fp-pick-list">@foreach(['Verify items against the approved report','Choose packaging and protect the contents','Confirm package count before completion'] as $step)<label><input type="checkbox"><span>{{ $step }}</span></label>@endforeach</div>
            <div class="vx-fp-drawer-footer"><button type="button" class="vx-fp-primary" wire:click="advanceDemo({{ $row['id'] }})" wire:loading.attr="disabled" wire:target="advanceDemo" @disabled(in_array($row['stage'],['blocked','complete']))>{{ ['ready'=>'Start picking','picking'=>'Move to packing','packing'=>'Complete sample work','blocked'=>'Stock exception pending','complete'=>'Completed'][$row['stage']] }}</button></div>
        </aside>
    </div>
    @endif
</div>
</x-filament-panels::page>
