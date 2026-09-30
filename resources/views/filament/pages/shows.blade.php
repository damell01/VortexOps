<x-filament-panels::page>
<div class="space-y-5 min-w-0">
@if(auth()->user()->isStreamer() && !auth()->user()->isAdmin() && auth()->user()->streamer)
    @livewire('create-manual-show',['streamer'=>auth()->user()->streamer])
@endif

<section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-sm font-semibold">Show period</h2><p class="text-xs text-gray-500">Choose the period you want to review.</p></div>
        <div class="flex rounded-lg border dark:border-gray-700">
            <button wire:click="$set('viewMode','list')" class="px-3 py-2 text-sm {{ $viewMode==='list'?'bg-primary-600 text-white':'' }}">List</button>
            <button wire:click="$set('viewMode','calendar')" class="px-3 py-2 text-sm {{ $viewMode==='calendar'?'bg-primary-600 text-white':'' }}">Calendar</button>
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
<section class="overflow-hidden rounded-xl border bg-white dark:border-gray-700 dark:bg-gray-900">
<div class="hidden overflow-x-auto md:block"><table class="w-full min-w-[860px] text-sm"><thead class="border-b bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800"><tr><th class="px-4 py-3 text-left">Show</th><th class="px-4 py-3 text-left">Streamer</th><th class="px-4 py-3 text-left">Schedule</th><th class="px-4 py-3 text-right">Gross</th><th class="px-4 py-3 text-right">Est. Net</th><th class="px-4 py-3 text-center">Workflow</th><th class="px-4 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y dark:divide-gray-800">
@forelse($this->shows as $show)<tr><td class="max-w-[320px] px-4 py-4"><a href="{{ $this->showUrl($show->id) }}" class="block truncate font-semibold hover:text-primary-600">{{ $show->title }}</a><div class="truncate text-xs text-gray-400">{{ $show->whatnot_show_id ?? 'Manual #'.$show->id }}</div></td><td class="max-w-[180px] px-4 py-4"><div class="truncate">{{ $show->streamers->pluck('name')->join(', ') ?: 'Unassigned' }}</div></td><td class="whitespace-nowrap px-4 py-4">{{ $show->show_date?->format('M j, Y') }}<div class="text-xs text-gray-400">{{ $show->start_time?->format('g:i A') ?: 'Time not set' }}</div></td><td class="whitespace-nowrap px-4 py-4 text-right font-semibold">${{ number_format((float)$show->gross_revenue,2) }}</td><td class="whitespace-nowrap px-4 py-4 text-right">${{ number_format((float)$show->whatnot_net,2) }}</td><td class="px-4 py-4 text-center"><span class="whitespace-nowrap rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold dark:bg-gray-800">{{ $this->workflowLabel($show) }}</span></td><td class="whitespace-nowrap px-4 py-4 text-right"><a href="{{ $this->showUrl($show->id) }}" class="rounded-lg border px-2.5 py-1.5 text-xs">View</a> @if(\App\Filament\Resources\ShowResource::canEdit($show))<a href="{{ $this->editUrl($show->id) }}" class="rounded-lg border px-2.5 py-1.5 text-xs">Edit</a>@endif</td></tr>
@empty<tr><td colspan="7" class="p-12 text-center text-gray-500">No shows match this period and filters.</td></tr>@endforelse
</tbody></table></div>
<div class="divide-y md:hidden dark:divide-gray-800">@forelse($this->shows as $show)<article class="min-w-0 p-4"><a href="{{ $this->showUrl($show->id) }}" class="block break-words font-semibold">{{ $show->title }}</a><div class="mt-1 text-xs text-gray-500">{{ $show->show_date?->format('M j, Y') }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'Unassigned' }}</div><div class="mt-3 grid grid-cols-2 gap-2 text-sm"><div><span class="text-xs text-gray-400">Gross</span><div>${{ number_format((float)$show->gross_revenue,2) }}</div></div><div><span class="text-xs text-gray-400">Est. Net</span><div>${{ number_format((float)$show->whatnot_net,2) }}</div></div><div class="col-span-2"><span class="text-xs text-gray-400">Workflow</span><div class="font-semibold">{{ $this->workflowLabel($show) }}</div></div></div></article>@empty<div class="p-10 text-center text-gray-500">No shows match.</div>@endforelse</div>
</section>
@endif
</div>
</x-filament-panels::page>