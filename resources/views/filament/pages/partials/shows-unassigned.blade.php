{{-- Unassigned queue, opened from the header's "Unassigned" slide-over. --}}
<div class="vx-unassigned space-y-2">
    @forelse($page->unassignedShows as $show)
        <div wire:key="unassigned-{{ $show->id }}" class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/60">
            <div class="break-words text-sm font-semibold leading-snug">{{ $show->title }}</div>
            <div class="mt-0.5 text-xs text-gray-500">{{ $show->show_date?->format('D, M j') }} · {{ $show->start_time?->format('g:i A') ?: 'Time not set' }}</div>
            <select aria-label="Assign streamer to {{ $show->title }}" wire:change="assignStreamer({{ $show->id }}, $event.target.value)" class="mt-2 w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-900">
                <option value="">Assign streamer…</option>
                @foreach($page->streamers as $st)<option value="{{ $st->id }}">{{ $st->name }}</option>@endforeach
            </select>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700">Every show this month has a streamer.</div>
    @endforelse
</div>
