@php
    use Illuminate\Support\Carbon;

    $shows       = $this->shows;
    $calDays     = $this->calendarDays;
    $monthLabel  = Carbon::create($this->year, $this->month, 1)->format('F Y');
    $today       = now()->format('Y-m-d');
    $isFuture    = Carbon::create($this->year, $this->month, 1)->gt(now()->endOfMonth());

    $statusColors = [
        'live'              => 'bg-violet-500',
        'completed'         => 'bg-emerald-500',
        'reconciled'        => 'bg-sky-500',
        'closed'            => 'bg-gray-400',
        'pending_approval'  => 'bg-amber-500',
        'draft'             => 'bg-gray-300',
    ];
@endphp

<x-filament::section>
    <x-slot name="heading">
        <div class="flex items-center justify-between w-full">
            <span>Show Calendar</span>
            <div class="flex items-center gap-2">
                <button
                    wire:click="previousMonth"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300 transition-colors"
                >
                    <x-heroicon-o-chevron-left class="h-4 w-4" />
                </button>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 w-36 text-center">{{ $monthLabel }}</span>
                <button
                    wire:click="nextMonth"
                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300 transition-colors"
                    @if ($isFuture) disabled @endif
                >
                    <x-heroicon-o-chevron-right class="h-4 w-4 {{ $isFuture ? 'opacity-30' : '' }}" />
                </button>
            </div>
        </div>
    </x-slot>

    {{-- Day-of-week header --}}
    <div class="grid grid-cols-7 mb-1">
        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
            <div class="py-1.5 text-center text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                {{ $dow }}
            </div>
        @endforeach
    </div>

    {{-- Calendar grid --}}
    <div class="grid grid-cols-7 gap-px bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden border border-gray-100 dark:border-gray-700">
        @foreach ($calDays as $day)
            @php
                $dateKey  = $day ? Carbon::create($this->year, $this->month, $day)->format('Y-m-d') : null;
                $dayShows = $day ? ($shows->get($dateKey) ?? collect()) : collect();
                $isToday  = $dateKey === $today;
            @endphp

            <div class="min-h-[80px] bg-white dark:bg-gray-900 p-1.5 {{ $isToday ? 'ring-2 ring-inset ring-primary-400' : '' }}">
                @if ($day)
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs font-medium {{ $isToday ? 'flex h-5 w-5 items-center justify-center rounded-full bg-primary-500 text-white text-[11px]' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $day }}
                        </span>
                        @if ($dayShows->count() > 2)
                            <span class="text-[10px] text-gray-400">+{{ $dayShows->count() - 2 }} more</span>
                        @endif
                    </div>

                    @foreach ($dayShows->take(2) as $show)
                        @php
                            $dot   = $statusColors[$show->status] ?? 'bg-gray-400';
                            $label = $show->whatnotChannel?->name ?? ($show->title ?? 'Show');
                            $rev   = $show->gross_revenue ? '$' . number_format($show->gross_revenue, 0) : '';
                        @endphp
                        <div
                            x-data
                            x-tooltip.raw="{{ e($label) }}{{ $rev ? ' · ' . $rev : '' }}"
                            class="group mb-0.5 flex items-center gap-1 rounded px-1 py-0.5 text-[11px] cursor-pointer
                                   hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                        >
                            <span class="h-1.5 w-1.5 rounded-full {{ $dot }} flex-shrink-0"></span>
                            <span class="truncate text-gray-600 dark:text-gray-300 font-medium">{{ Str::limit($label, 14) }}</span>
                            @if ($rev)
                                <span class="ml-auto text-emerald-600 dark:text-emerald-400 text-[10px] font-semibold flex-shrink-0">{{ $rev }}</span>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        @endforeach
    </div>

    {{-- Legend --}}
    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
        @foreach ([
            ['bg-emerald-500', 'Completed'],
            ['bg-violet-500',  'Live'],
            ['bg-sky-500',     'Reconciled'],
            ['bg-amber-500',   'Pending Approval'],
            ['bg-gray-400',    'Closed'],
        ] as [$dot, $label])
            <span class="flex items-center gap-1">
                <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
                {{ $label }}
            </span>
        @endforeach
    </div>
</x-filament::section>
