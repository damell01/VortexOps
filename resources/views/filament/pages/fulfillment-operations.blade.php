<x-filament-panels::page>
    @php($data = $this->metrics())
    <div wire:poll.30s class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (['Active stations' => $data['runs'], 'Packers working' => $data['packers'], 'Buyers waiting' => $data['waiting'], 'Open issues' => $data['open']] as $label => $value)
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold text-violet-700 dark:text-violet-300">{{ $value }}</p>
                </div>
            @endforeach
        </div>
        <div class="grid gap-5 lg:grid-cols-2">
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <h2 class="text-lg font-bold">Packing stations</h2>
                <div class="mt-4 space-y-3">
                    @foreach($data['stations'] as $station)
                        <div class="flex items-center justify-between rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                            <div><p class="font-semibold">{{ $station->name }}</p><p class="text-xs text-gray-500">{{ $station->run_id ? 'Show #'.$station->show_id.' · Started '.$station->started_at : 'Available' }}</p></div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $station->run_id ? 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-100' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">{{ $station->run_id ? 'Packing' : ($station->active ? 'Ready' : 'Offline') }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                <h2 class="text-lg font-bold">7-day packing productivity</h2>
                <p class="text-xs text-gray-500">Operational counts only; not payroll-approved earnings.</p>
                <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($data['productivity'] as $employee)
                        <div class="flex items-center justify-between py-3"><span class="font-medium">{{ $employee->name }}</span><span class="text-sm text-gray-600 dark:text-gray-300">{{ $employee->buyers_packed }} buyers · {{ $employee->items_packed }} items</span></div>
                    @empty
                        <p class="py-4 text-sm text-gray-500">No completed buyer packing records this week.</p>
                    @endforelse
                </div>
            </section>
        </div>
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
            <h2 class="text-lg font-bold">Issues requiring attention</h2>
            <div class="mt-4 space-y-3">
                @forelse($data['flags'] as $flag)
                    <div wire:key="flag-{{ $flag->id }}" class="rounded-xl border border-amber-200 p-4 dark:border-amber-800">
                        <div class="flex flex-wrap justify-between gap-2"><div><p class="font-semibold">{{ str_replace('_', ' ', ucfirst($flag->type)) }} · Show #{{ $flag->show_id }}</p><p class="mt-1 text-sm">{{ $flag->details }}</p><p class="mt-1 text-xs text-gray-500">Reported {{ $flag->created_at }}</p></div><button type="button" wire:click="$set('selectedFlag', {{ $flag->id }})" class="rounded-lg bg-violet-700 px-3 py-2 text-sm font-semibold text-white">Resolve</button></div>
                        @if($selectedFlag === (int) $flag->id)
                            <div class="mt-3 space-y-2"><label class="block text-sm font-medium" for="resolution-{{ $flag->id }}">Resolution notes</label><textarea id="resolution-{{ $flag->id }}" wire:model="resolution" rows="2" class="w-full rounded-lg border-gray-300 dark:bg-gray-800"></textarea>@error('resolution') <p class="text-sm text-red-600">{{ $message }}</p> @enderror<button type="button" wire:click="resolveFlag({{ $flag->id }})" class="rounded-lg bg-violet-700 px-4 py-2 text-sm font-semibold text-white">Save resolution</button></div>
                        @endif
                    </div>
                @empty
                    <p class="py-4 text-sm text-gray-500">No unresolved fulfillment issues.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
