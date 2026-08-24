<x-filament-panels::page>
    <div class="space-y-5">
        {{--
            The streamer relation is set up by hand, so a user can hold the
            role with nothing linked. Logging a show needs a streamer to log it
            against, so there is nothing useful to show them here.
        --}}
        @if(auth()->user()->isStreamer() && !auth()->user()->isAdmin() && auth()->user()->streamer)
            @livewire('create-manual-show', ['streamer' => auth()->user()->streamer])
        @endif

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Find shows</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Filter the same list by schedule, workflow, or streamer.</p>
                </div>
                <button type="button" wire:click="clearFilters" class="text-sm font-medium text-primary-600 hover:underline">Clear all</button>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <input type="search" wire:model.live.debounce.300ms="searchQuery" placeholder="Search title or Whatnot ID…"
                    class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />

                <select wire:model.live="filterTimeframe" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="all">All dates</option>
                    <option value="upcoming">Upcoming</option>
                    <option value="past">Past / due</option>
                    <option value="attention">Needs submission</option>
                </select>

                <select wire:model.live="filterStatus" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="all">All workflow statuses</option>
                    @foreach(\App\Models\Show::statusLabels() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if(auth()->user()->isAdmin())
                    <select wire:model.live="filterStreamer" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        <option value="">All streamers</option>
                        @foreach($this->streamers as $streamer)
                            <option value="{{ $streamer->id }}">{{ $streamer->name }}</option>
                        @endforeach
                    </select>
                @endif

                <select wire:model.live="sortBy" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="date">Date — newest</option>
                    <option value="oldest">Date — oldest</option>
                    <option value="revenue">Revenue — highest</option>
                </select>
            </div>
        </div>

        @php
            $stats = $this->shows;
            $upcoming = $stats->filter(fn ($s) => !$this->isShowDue($s))->count();
            $due = $stats->filter(fn ($s) => $this->isShowDue($s))->count();
            $pending = $stats->filter(fn ($s) => $this->isShowDue($s) && !$s->streamerLogEntry && !in_array($s->status, ['closed','cancelled']))->count();
            $shipmentTotal = $stats->sum('shipments_count');
            $revenue = $stats->filter(fn ($s) => $this->isShowDue($s))->sum('gross_revenue');
        @endphp

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            @foreach([
                ['Upcoming', $upcoming, 'Scheduled ahead'],
                ['Past / Due', $due, 'Shows that have happened'],
                ['Needs Submission', $pending, 'Actionable only'],
                ['Shipments', number_format($shipmentTotal), 'Across listed shows'],
                ['Revenue', '$'.number_format($revenue, 0), 'Past / due shows'],
            ] as [$label,$value,$sub])
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                    <div class="mt-1 text-xs text-gray-400">{{ $sub }}</div>
                </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Show</th>
                            <th class="px-4 py-3 text-left">Streamer</th>
                            <th class="px-4 py-3 text-left">Schedule</th>
                            <th class="px-4 py-3 text-right">Revenue</th>
                            <th class="px-4 py-3 text-center">Items</th>
                            <th class="px-4 py-3 text-center">Shipments</th>
                            <th class="px-4 py-3 text-center">Form</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->shows as $show)
                            @php
                                $dueNow = $this->isShowDue($show);
                                $log = $show->streamerLogEntry;
                            @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-4 align-top">
                                    <a href="{{ $this->showUrl($show->id) }}" class="font-semibold text-gray-950 hover:text-primary-600 dark:text-white">{{ $show->title }}</a>
                                    <div class="mt-1 max-w-[320px] truncate text-xs text-gray-400">{{ $show->whatnot_show_id ?? 'Manual show #'.$show->id }}</div>
                                </td>
                                <td class="px-4 py-4 align-top">
                                    @forelse($show->streamers as $streamer)
                                        <span class="mr-1 inline-flex rounded-full bg-violet-50 px-2 py-1 text-xs font-medium text-violet-700 dark:bg-violet-950 dark:text-violet-200">{{ $streamer->name }}</span>
                                    @empty
                                        <span class="text-xs text-amber-600">Unassigned</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-medium text-gray-800 dark:text-gray-200">{{ $show->show_date?->format('M j, Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $show->start_time?->format('g:i A') ?: 'Time not set' }}</div>
                                    <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $dueNow ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200' }}">{{ $dueNow ? 'Past / due' : 'Upcoming' }}</span>
                                </td>
                                <td class="px-4 py-4 text-right align-top font-semibold">${{ number_format((float)$show->gross_revenue, 2) }}</td>
                                <td class="px-4 py-4 text-center align-top">{{ $show->orders_count }}</td>
                                <td class="px-4 py-4 text-center align-top">
                                    <a href="{{ $this->shipmentsUrl($show->id) }}" class="font-semibold text-primary-600 hover:underline">{{ $show->shipments_count }}</a>
                                    @if($show->pending_shipments_count)
                                        <div class="text-xs text-amber-600">{{ $show->pending_shipments_count }} open</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center align-top">
                                    @if(!$dueNow)
                                        <span class="rounded-full bg-blue-50 px-2 py-1 text-xs text-blue-700 dark:bg-blue-950 dark:text-blue-200">Not due</span>
                                    @elseif(!$log)
                                        <span class="rounded-full bg-amber-50 px-2 py-1 text-xs text-amber-700 dark:bg-amber-950 dark:text-amber-200">Awaiting submission</span>
                                    @elseif($log->status === 'admin_approved')
                                        <span class="rounded-full bg-green-50 px-2 py-1 text-xs text-green-700 dark:bg-green-950 dark:text-green-200">Approved</span>
                                    @elseif($log->status === 'changes_requested')
                                        <span class="rounded-full bg-orange-50 px-2 py-1 text-xs text-orange-700 dark:bg-orange-950 dark:text-orange-200">Changes requested</span>
                                    @else
                                        <span class="rounded-full bg-sky-50 px-2 py-1 text-xs text-sky-700 dark:bg-sky-950 dark:text-sky-200">Submitted</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 align-top">
                                    <div class="flex justify-end gap-2 whitespace-nowrap">
                                        <a href="{{ $this->showUrl($show->id) }}" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">View</a>
                                        @if(\App\Filament\Resources\ShowResource::canEdit($show))
                                            <a href="{{ $this->editUrl($show->id) }}" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">Edit</a>
                                        @endif
                                        <a href="{{ $this->shipmentsUrl($show->id) }}" class="rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-medium text-white">Shipments</a>
                                        @if(auth()->user()?->isAdmin())
                                            <button type="button" wire:click="deleteShow({{ $show->id }})" wire:confirm="Delete this show and ALL related imported orders, shipments, assignments, logs and workflow data? This cannot be undone." class="rounded-lg px-2.5 py-1.5 text-xs font-medium text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-950">Delete</button>
                                        @endif
                                    </div>
                                    @if($dueNow && !$log && auth()->user()?->isAdmin())
                                        <button type="button" wire:click="requestFormSubmission({{ $show->id }})" class="mt-2 text-xs font-medium text-amber-600 hover:underline">Request submission</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-12 text-center text-gray-500">No shows match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-gray-200 md:hidden dark:divide-gray-800">
                @forelse($this->shows as $show)
                    @php $dueNow = $this->isShowDue($show); $log = $show->streamerLogEntry; @endphp
                    <article class="p-4">
                        <a href="{{ $this->showUrl($show->id) }}" class="block text-base font-semibold leading-snug text-gray-950 dark:text-white">{{ $show->title }}</a>
                        <div class="mt-1 truncate text-xs text-gray-400">{{ $show->whatnot_show_id ?? 'Manual show #'.$show->id }}</div>

                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="rounded-full {{ $dueNow ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200' }} px-2 py-1">{{ $dueNow ? 'Past / due' : 'Upcoming' }}</span>
                            <span class="rounded-full bg-violet-50 px-2 py-1 text-violet-700 dark:bg-violet-950 dark:text-violet-200">{{ $show->streamers->pluck('name')->join(', ') ?: 'Unassigned' }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ \App\Models\Show::statusLabels()[$show->status] ?? ucfirst($show->status) }}</span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-3 border-y border-gray-100 py-3 text-sm dark:border-gray-800">
                            <div><div class="text-xs text-gray-400">Date</div><div>{{ $show->show_date?->format('M j, Y') }} {{ $show->start_time?->format('g:i A') }}</div></div>
                            <div><div class="text-xs text-gray-400">Revenue</div><div class="font-semibold">${{ number_format((float)$show->gross_revenue, 2) }}</div></div>
                            <div><div class="text-xs text-gray-400">Items</div><div>{{ $show->orders_count }}</div></div>
                            <div><div class="text-xs text-gray-400">Shipments</div><a href="{{ $this->shipmentsUrl($show->id) }}" class="font-semibold text-primary-600">{{ $show->shipments_count }}{{ $show->pending_shipments_count ? ' · '.$show->pending_shipments_count.' open' : '' }}</a></div>
                            <div class="col-span-2"><div class="text-xs text-gray-400">Form</div><div>{{ !$dueNow ? 'Not due yet' : (!$log ? 'Awaiting submission' : ($log->status === 'admin_approved' ? 'Approved' : ($log->status === 'changes_requested' ? 'Changes requested' : 'Submitted'))) }}</div></div>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <a href="{{ $this->showUrl($show->id) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-center text-xs font-medium dark:border-gray-700">View</a>
                            @if(\App\Filament\Resources\ShowResource::canEdit($show))
                                <a href="{{ $this->editUrl($show->id) }}" class="rounded-lg border border-gray-200 px-3 py-2 text-center text-xs font-medium dark:border-gray-700">Edit</a>
                            @else
                                <span></span>
                            @endif
                            <a href="{{ $this->shipmentsUrl($show->id) }}" class="rounded-lg bg-primary-600 px-3 py-2 text-center text-xs font-medium text-white">Shipments</a>
                        </div>

                        @if($dueNow && !$log && auth()->user()?->isAdmin())
                            <button type="button" wire:click="requestFormSubmission({{ $show->id }})" class="mt-3 w-full rounded-lg bg-amber-50 px-3 py-2 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-200">Request submission</button>
                        @endif
                        @if(auth()->user()?->isAdmin())
                            <button type="button" wire:click="deleteShow({{ $show->id }})" wire:confirm="Delete this show and ALL related imported data? This cannot be undone." class="mt-2 w-full rounded-lg px-3 py-2 text-xs font-medium text-danger-600">Delete show</button>
                        @endif
                    </article>
                @empty
                    <div class="p-10 text-center text-gray-500">No shows match these filters.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
