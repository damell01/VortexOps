<x-filament-panels::page>
<div class="vx-shows-redesign space-y-4 min-w-0">
@if(auth()->user()->isStreamer() && !auth()->user()->isAdmin() && auth()->user()->streamer)
    @livewire('create-manual-show',['streamer'=>auth()->user()->streamer])
@endif

@if(auth()->user()->isAdmin())
<section id="unassigned-shows" class="vx-assignment-queue rounded-xl border p-4">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3"><div><h2 class="font-bold">Unassigned Shows</h2><p class="text-xs text-gray-500">Shows in this period that still need a streamer. Assigning one removes it from this queue automatically.</p></div><span class="inline-flex min-w-7 items-center justify-center rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-200">{{ $this->unassignedShows->count() }}</span></div>
        <button type="button" onclick="document.getElementById('unassigned-shows')?.scrollIntoView({behavior:'smooth'})" class="rounded-lg border bg-white px-3 py-2 text-xs font-semibold dark:bg-gray-900">Review unassigned</button>
    </div>
    @if($this->unassignedShows->isEmpty())
        <div class="rounded-lg border border-dashed bg-white p-5 text-center text-sm text-gray-500 dark:bg-gray-900">All shows in this period have a streamer assigned.</div>
    @else
        <div class="vx-assignment-grid">
        @foreach($this->unassignedShows as $show)
            <div class="vx-assignment-item flex min-w-0 flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1"><div class="break-words font-semibold leading-snug">{{ $show->title }}</div><div class="mt-1 text-xs text-gray-500">{{ $show->show_date?->format('M j, Y') }} · {{ $show->start_time?->format('g:i A') ?: 'Time not set' }}</div></div>
                <select aria-label="Assign streamer to {{ $show->title }}" onchange="if(this.value){$wire.assignStreamer({{ $show->id }}, this.value)}" class="min-w-[180px] rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Assign streamer…</option>
                    @foreach($this->streamers as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach
                </select>
            </div>
        @endforeach
        </div>
    @endif
</section>
@endif

<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div><div class="text-[10px] font-extrabold uppercase tracking-[.12em] text-primary-600">Shows workspace</div><h2 class="mt-1 text-lg font-bold">Find and work shows</h2><p class="text-xs text-gray-500">Period, filters, assignment, and show actions in one place.</p></div>
        <div class="flex rounded-lg border bg-gray-50 p-1 dark:border-gray-700 dark:bg-gray-800">
            <button wire:click="$set('viewMode','list')" class="rounded-md px-3 py-1.5 text-xs font-bold {{ $viewMode==='list'?'bg-white text-primary-700 shadow-sm dark:bg-gray-900':'' }}">Cards</button>
            <button wire:click="$set('viewMode','calendar')" class="rounded-md px-3 py-1.5 text-xs font-bold {{ $viewMode==='calendar'?'bg-white text-primary-700 shadow-sm dark:bg-gray-900':'' }}">Calendar</button>
        </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[auto_180px_160px_160px_auto_minmax(180px,1fr)] lg:items-end">
        <button wire:click="previousPeriod" class="rounded-lg border px-3 py-2 dark:border-gray-700">‹</button>
        <label class="min-w-0 text-xs text-gray-500">Period<select wire:model.live="datePreset" class="mt-1 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800"><option value="this_week">This week</option><option value="last_week">Last week</option><option value="this_month">This month</option><option value="last_month">Last month</option><option value="last_30">Last 30 days</option><option value="custom">Custom</option></select></label>
        <label class="min-w-0 text-xs text-gray-500">From<input type="date" wire:model.live="dateFrom" wire:change="$set('datePreset','custom')" class="mt-1 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800"></label>
        <label class="min-w-0 text-xs text-gray-500">To<input type="date" wire:model.live="dateTo" wire:change="$set('datePreset','custom')" class="mt-1 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800"></label>
        <button wire:click="nextPeriod" class="rounded-lg border px-3 py-2 dark:border-gray-700">›</button>
        <div class="min-w-0 rounded-lg bg-gray-50 px-3 py-2 text-center text-sm font-semibold dark:bg-gray-800">{{ $this->dateRangeLabel() }}</div>
    </div>
</section>

<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
    <div class="mb-3 flex items-center justify-between gap-3"><div><h2 class="text-sm font-semibold">Filter shows</h2><p class="text-xs text-gray-500">Filters apply to list and calendar views.</p></div><button wire:click="clearFilters" class="text-sm font-medium text-primary-600">Reset</button></div>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <input type="search" wire:model.live.debounce.300ms="searchQuery" placeholder="Search title or Whatnot ID…" class="min-w-0 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800">
        <select wire:model.live="filterTimeframe" class="min-w-0 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800"><option value="all">All in period</option><option value="upcoming">Upcoming</option><option value="past">Past / due</option><option value="attention">Needs submission</option></select>
        <select wire:model.live="filterStatus" class="min-w-0 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800"><option value="all">All statuses</option>@foreach(\App\Models\Show::statusLabels() as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        @if(auth()->user()->isAdmin())<select wire:model.live="filterStreamer" class="min-w-0 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800"><option value="">All streamers</option>@foreach($this->streamers as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach</select>@endif
        <select wire:model.live="sortBy" class="min-w-0 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800"><option value="date">Date — newest</option><option value="oldest">Date — oldest</option><option value="revenue">Revenue — highest</option></select>
    </div>
</section>

@php
$stats=$this->shows;
$completed=$stats->filter(fn($s)=>$this->workflowLabel($s)==='Completed')->count();
$gross=(float)$stats->sum('gross_revenue');
$net=(float)$stats->sum('whatnot_net');
@endphp
<div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
@foreach([['Shows',$stats->count(),'In selected period'],['Completed',$completed,'Workflow complete'],['Whatnot Gross','$'.number_format($gross,0),'Synced show sales'],['Est. Whatnot Net','$'.number_format($net,0),'Estimated earnings']] as [$label,$value,$help])
<div class="min-w-0 rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="text-xs text-gray-500">{{ $label }}</div><div class="mt-1 truncate text-xl font-bold sm:text-2xl">{{ $value }}</div><div class="mt-1 text-xs text-gray-400">{{ $help }}</div></div>
@endforeach
</div>

@if($viewMode==='calendar')
<section class="hidden overflow-hidden rounded-xl border bg-white md:block dark:border-gray-700 dark:bg-gray-900">
<div class="overflow-x-auto"><div class="min-w-[900px] p-4">
<div class="grid grid-cols-7 border-b text-center text-xs font-semibold uppercase text-gray-500">@foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)<div class="p-2">{{ $d }}</div>@endforeach</div>
@foreach($this->calendarWeeks as $week)<div class="grid grid-cols-7">@foreach($week as $day)<div class="min-h-32 min-w-0 border-b border-r p-2 dark:border-gray-800 {{ !$day['in_range']?'opacity-35 bg-gray-50 dark:bg-gray-950':'' }}"><div class="mb-2 text-xs font-semibold">{{ $day['date']->format('M j') }}</div><div class="space-y-1">@foreach($day['shows'] as $show)<a href="{{ $this->showUrl($show->id) }}" class="block min-w-0 rounded-lg border p-2 text-xs dark:border-gray-700"><div class="truncate font-semibold">{{ $show->title }}</div><div class="truncate text-gray-500">{{ $show->streamers->pluck('name')->join(', ') ?: 'Unassigned' }}</div></a>@endforeach</div></div>@endforeach</div>@endforeach
</div></div></section>
<section class="space-y-3 md:hidden">
@php $agendaDays=$this->calendarWeeks->flatten(1)->filter(fn($d)=>$d['in_range'] && $d['shows']->isNotEmpty()); @endphp
@forelse($agendaDays as $day)<article class="rounded-xl border bg-white p-3 dark:border-gray-700 dark:bg-gray-900"><div class="mb-2 font-bold">{{ $day['date']->format('D, M j') }}</div><div class="space-y-2">@foreach($day['shows'] as $show)<a href="{{ $this->showUrl($show->id) }}" class="block rounded-lg border p-3 dark:border-gray-700"><div class="break-words font-semibold">{{ $show->title }}</div><div class="mt-1 text-xs text-gray-500">{{ $show->streamers->pluck('name')->join(', ') ?: 'Unassigned' }}</div></a>@endforeach</div></article>@empty<div class="rounded-xl border p-8 text-center text-sm text-gray-500">No shows in this period.</div>@endforelse
</section>
@else
@php $pager=$this->paginatedShows(); @endphp
<section class="space-y-3">
<div class="flex items-center justify-between gap-2 px-1"><div class="text-sm text-gray-500">Showing {{ $pager['items']->count() }} of {{ $pager['total'] }} shows</div><div class="text-xs font-semibold text-gray-400">12 per page</div></div>
@if($pager['items']->isEmpty())<div class="rounded-xl border bg-white p-12 text-center text-gray-500 dark:border-gray-700 dark:bg-gray-900">No shows match this period and filters.</div>@else
<div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
@foreach($pager['items'] as $show)
@php $workflow=$this->workflowLabel($show); $unassigned=$show->streamers->isEmpty(); @endphp
<article wire:key="show-card-{{ $show->id }}" class="flex min-w-0 flex-col rounded-xl border bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 {{ $unassigned?'border-amber-300 dark:border-amber-800':'' }}">
<div class="flex items-start justify-between gap-3"><span class="rounded-full bg-gray-100 px-2 py-1 text-[11px] font-bold dark:bg-gray-800">{{ $workflow }}</span><div class="shrink-0 text-right text-xs text-gray-500">{{ $show->show_date?->format('M j, Y') }}<div>{{ $show->start_time?->format('g:i A') ?: 'Time not set' }}</div></div></div>
<a href="{{ $this->showUrl($show->id) }}" class="mt-3 line-clamp-2 min-h-10 break-words text-sm font-bold hover:text-primary-600">{{ $show->title }}</a><div class="mt-1 truncate text-[11px] text-gray-400">{{ $show->whatnot_show_id ?? 'Manual #'.$show->id }}</div>
<div class="mt-4 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/70"><div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Streamer</div>
@if($unassigned && auth()->user()->isAdmin())<select aria-label="Assign streamer to {{ $show->title }}" onchange="if(this.value){$wire.assignStreamer({{ $show->id }}, this.value)}" class="mt-1 w-full rounded-lg border-amber-300 bg-white text-sm font-semibold dark:border-amber-700 dark:bg-gray-900"><option value="">Assign streamer...</option>@foreach($this->streamers as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach</select>@else<div class="mt-1 truncate text-sm font-semibold">{{ $show->streamers->pluck('name')->join(', ') ?: 'Unassigned' }}</div>@endif</div>
<div class="mt-3 grid grid-cols-2 gap-2"><div class="rounded-lg border p-3 dark:border-gray-700"><div class="text-[10px] uppercase text-gray-400">Gross</div><div class="mt-1 font-bold">${{ number_format((float)$show->gross_revenue,2) }}</div></div><div class="rounded-lg border p-3 dark:border-gray-700"><div class="text-[10px] uppercase text-gray-400">Est. Net</div><div class="mt-1 font-bold">${{ number_format((float)$show->whatnot_net,2) }}</div></div></div>
<div class="mt-auto flex gap-2 pt-4"><a href="{{ $this->showUrl($show->id) }}" class="flex-1 rounded-lg bg-primary-600 px-3 py-2 text-center text-xs font-bold text-white">View Show</a>@if(\App\Filament\Resources\ShowResource::canEdit($show))<a href="{{ $this->editUrl($show->id) }}" class="rounded-lg border px-4 py-2 text-center text-xs font-bold">Edit</a>@endif</div></article>
@endforeach
</div>
@if($pager['lastPage']>1)<nav class="flex items-center justify-between gap-3 rounded-xl border bg-white p-3 dark:border-gray-700 dark:bg-gray-900"><button wire:click="setShowPage({{ max(1,$pager['page']-1) }})" @disabled($pager['page']===1) class="rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-40">Previous</button><div class="text-sm font-semibold">Page {{ $pager['page'] }} of {{ $pager['lastPage'] }}</div><button wire:click="setShowPage({{ min($pager['lastPage'],$pager['page']+1) }})" @disabled($pager['page']===$pager['lastPage']) class="rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-40">Next</button></nav>@endif
@endif
</section>
@endif
</div>
</x-filament-panels::page>