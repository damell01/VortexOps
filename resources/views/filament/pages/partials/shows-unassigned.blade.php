{{-- Unassigned queue, opened from the header's "Unassigned" slide-over. --}}
<div class="vx-unassigned space-y-2">
    @if($page->detectedHostGroups->isNotEmpty())
        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
            <p class="text-sm font-semibold">Detected streamer groups</p>
            <p class="mt-1 text-xs text-gray-500">Suggestions from explicit “w/” or “with” in titles. Review before assigning. Multiple-host and unclear titles stay below for manual assignment. Showing up to 100 unassigned shows in this month.</p>
            @foreach($page->detectedHostGroups as $group)
                <button type="button" wire:key="host-{{ $group['key'] }}" wire:click="mountAction('reviewDetectedHost', {{ \Illuminate\Support\Js::from(['group' => $group['key']]) }})" class="mt-2 flex min-h-11 w-full items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 text-left dark:border-gray-700">
                    <span class="font-semibold">{{ $group['name'] }}</span><span class="text-xs">{{ $group['shows']->count() }} shows · Review</span>
                </button>
            @endforeach
        </div>
    @endif
    @if($page->unassignedShows->isNotEmpty())
    <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
        <p class="text-sm font-semibold">Assign selected shows</p>
        <p class="mt-1 text-xs text-gray-500">Select shows below, then choose one streamer.</p>
        <select wire:model="bulkStreamerId" aria-label="Streamer for selected shows" class="mt-2 w-full rounded-lg dark:bg-gray-900">
            <option value="">Choose streamer…</option>
            @foreach($page->streamers as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach
        </select>
        <button type="button" wire:click="assignSelectedShows" wire:confirm="Assign all selected unassigned shows to this streamer?" wire:loading.attr="disabled" wire:target="assignSelectedShows" class="mt-2 min-h-11 w-full rounded-lg bg-primary-600 px-3 text-sm font-semibold text-white">
            <span wire:loading.remove wire:target="assignSelectedShows">Assign selected</span><span wire:loading wire:target="assignSelectedShows">Assigning…</span>
        </button>
        @error('selectedUnassignedShows')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        @error('bulkStreamerId')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    @endif
    @forelse($page->unassignedShows as $show)
        <div wire:key="unassigned-{{ $show->id }}" class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
            <label class="mb-2 flex items-center gap-2 text-xs"><input type="checkbox" wire:model="selectedUnassignedShows" value="{{ $show->id }}" aria-label="Select {{ $show->title }}">Select show</label>
            <div class="break-words text-sm font-semibold leading-snug">{{ $show->title }}</div>
            <div class="mt-0.5 text-xs text-gray-500">{{ $show->show_date?->format('D, M j') }} · {{ $show->start_time?->format('g:i A') ?: 'Time not set' }}</div>
            <select aria-label="Assign streamer to {{ $show->title }}" wire:loading.attr="disabled" wire:target="assignStreamer" wire:change="assignStreamer({{ $show->id }}, $event.target.value)" class="mt-2 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-900">
                <option value="">Assign streamer…</option>
                @foreach($page->streamers as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach
            </select>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">Every show this month has a streamer.</div>
    @endforelse
</div>
