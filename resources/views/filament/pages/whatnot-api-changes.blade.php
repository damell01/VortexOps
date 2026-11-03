<x-filament-panels::page>
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Latest runs</h2>
        @forelse ($this->runs as $run)
            <div class="mt-3 border-t border-gray-100 pt-3 text-sm dark:border-gray-800">
                <div class="font-medium text-gray-950 dark:text-white">{{ $run['channel'] }} — {{ $run['kind'] }} · {{ str_replace('_', ' ', $run['status']) }} · {{ $run['at'] }}</div>
                @foreach ($run['errors'] as $error)
                    <div class="text-danger-600 dark:text-danger-400">{{ $error }}</div>
                @endforeach
                @forelse ($run['flags'] as $flag)
                    <div class="text-warning-700 dark:text-warning-400">To review: {{ $flag }}</div>
                @empty
                    <div class="text-gray-500 dark:text-gray-400">Nothing left for review.</div>
                @endforelse
            </div>
        @empty
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No backfill or sync has run yet.</p>
        @endforelse
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Channel
                <select wire:model.live="channel" class="mt-1 block rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                    <option value="">All channels</option>
                    @foreach ($this->channels as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Field
                <select wire:model.live="field" class="mt-1 block rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                    <option value="">All fields</option>
                    @foreach ($this->fields as $name)<option value="{{ $name }}">{{ str_replace('_', ' ', $name) }}</option>@endforeach
                </select>
            </label>
            <label class="min-w-0 flex-1 text-sm font-medium text-gray-700 dark:text-gray-300">Show title
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search shows…" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
            </label>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr><th class="py-2 pr-3">When</th><th class="py-2 pr-3">Show</th><th class="py-2 pr-3">Field</th><th class="py-2 pr-3">Was</th><th class="py-2 pr-3">Now</th><th class="py-2">Note</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->changes as $change)
                        <tr>
                            <td class="py-2 pr-3 whitespace-nowrap text-gray-500">{{ $change->created_at?->format('M j, g:ia') }}</td>
                            <td class="py-2 pr-3"><a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $change->show_id]) }}" class="font-medium text-primary-600 hover:underline">{{ $change->show_title }}</a>
                                <div class="text-xs text-gray-500">{{ \Illuminate\Support\Carbon::parse($change->show_date)->format('M j, Y') }} · {{ $change->channel_name ?? '—' }}</div></td>
                            <td class="py-2 pr-3">{{ str_replace('_', ' ', $change->field_name) }}</td>
                            <td class="py-2 pr-3 text-gray-500">{{ $change->old_value ?? '—' }}</td>
                            <td class="py-2 pr-3 font-medium">{{ $change->new_value ?? '—' }}</td>
                            <td class="py-2 text-xs text-gray-500">{{ $change->notes }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-500">No changes from the Whatnot API yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $this->changes->links() }}</div>
    </section>
</x-filament-panels::page>
