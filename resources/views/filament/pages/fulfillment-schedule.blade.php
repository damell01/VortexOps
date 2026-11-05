<x-filament-panels::page>
    @php
        ['start' => $start, 'shifts' => $shifts, 'employees' => $employees, 'stations' => $stations, 'nextShift' => $nextShift] = $this->scheduleData();
    @endphp
    <div class="space-y-5">
        @if(! $this->isManager())
            <section class="rounded-2xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-800 dark:bg-violet-950/30" aria-label="Next assigned shift">
                <p class="text-xs font-bold uppercase tracking-wide text-violet-700 dark:text-violet-300">Your next shift</p>
                @if($nextShift)
                    <p class="mt-2 text-lg font-semibold">{{ \Carbon\Carbon::parse($nextShift->starts_at)->format('D, M j · g:i A') }} – {{ \Carbon\Carbon::parse($nextShift->ends_at)->format('g:i A') }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-300">Station: {{ $nextShift->station_name ?? 'To be assigned' }}</p>
                @else
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">No upcoming shift assigned yet.</p>
                @endif
            </section>
        @endif
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-violet-600 dark:text-violet-400">Fulfillment · Team schedule</p>
                <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ $start->format('M j') }} – {{ $start->addDays(6)->format('M j, Y') }}</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->isManager() ? 'Team shifts and station assignments' : 'Your upcoming fulfillment shifts' }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="changeWeek(-1)" aria-label="Previous week" class="min-h-11 rounded-xl border border-gray-300 px-4 font-medium text-gray-900 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-600 dark:border-gray-600 dark:text-white dark:hover:bg-gray-800">← Previous</button>
                <button type="button" wire:click="changeWeek(1)" aria-label="Next week" class="min-h-11 rounded-xl border border-gray-300 px-4 font-medium text-gray-900 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-600 dark:border-gray-600 dark:text-white dark:hover:bg-gray-800">Next →</button>
                @if($this->isManager())
                    <button type="button" wire:click="copyPreviousWeek" wire:confirm="Copy shifts from the previous week into this week? Existing overlapping shifts will be skipped." class="min-h-11 rounded-xl bg-violet-600 px-4 font-semibold text-white hover:bg-violet-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-violet-600">Copy previous week</button>
                @endif
            </div>
        </div>

        @if($this->isManager())
            <form wire:submit="createShift" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-4 font-semibold text-gray-950 dark:text-white">Add shift</h3>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Employee
                        <select wire:model="employeeId" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Select employee</option>
                            @foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Station
                        <select wire:model="stationId" class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Unassigned</option>
                            @foreach($stations as $station)<option value="{{ $station->id }}">{{ $station->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Starts
                        <input wire:model="startsAt" type="datetime-local" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"/>
                    </label>
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Ends
                        <input wire:model="endsAt" type="datetime-local" required class="mt-1 min-h-11 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"/>
                    </label>
                    <div class="flex items-end"><button type="submit" wire:loading.attr="disabled" wire:target="createShift" class="min-h-11 w-full rounded-xl bg-violet-600 px-4 font-semibold text-white hover:bg-violet-700 disabled:opacity-50">Save shift</button></div>
                </div>
                @error('employeeId')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('startsAt')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('endsAt')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </form>
        @endif

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-7">
            @for($day = 0; $day < 7; $day++)
                @php $date = $start->addDays($day); $dayShifts = $shifts->filter(fn ($shift) => \Illuminate\Support\Carbon::parse($shift->starts_at)->toDateString() === $date->toDateString()); @endphp
                <section class="min-h-44 rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900" aria-label="{{ $date->format('l, F j') }}">
                    <h3 class="mb-3 border-b border-gray-100 pb-2 text-sm font-bold text-gray-950 dark:border-gray-700 dark:text-white">{{ $date->format('D') }} <span class="font-normal text-gray-500">{{ $date->format('M j') }}</span></h3>
                    @forelse($dayShifts as $shift)
                        <div class="mb-2 rounded-xl border-l-4 border-violet-600 bg-violet-50 p-3 text-sm dark:bg-violet-950/30">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $shift->employee_name }}</p>
                            <p class="mt-1 text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Carbon::parse($shift->starts_at)->format('g:i A') }} – {{ \Illuminate\Support\Carbon::parse($shift->ends_at)->format('g:i A') }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $shift->station_name ?? 'Station TBD' }}</p>
                        </div>
                    @empty
                        <p class="py-4 text-center text-xs text-gray-400">No shifts</p>
                    @endforelse
                </section>
            @endfor
        </div>
    </div>
</x-filament-panels::page>
