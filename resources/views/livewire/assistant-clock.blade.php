<section class="vxw-card" data-vx="assistant-clock">
    <div class="vxw-card-body flex flex-wrap items-center justify-between gap-4">
        <div><h2 class="font-semibold">Time clock</h2>
            @if($entry)
                <p class="mt-1 text-sm text-gray-500">Clocked in at {{ $entry->clocked_in_at->copy()->timezone(auth()->user()->timezone ?: \App\Support\BusinessTime::zone())->format('g:i A') }}</p>
            @else
                <p class="mt-1 text-sm text-gray-500">Not clocked in</p>
            @endif
        </div>
        <button type="button" wire:click="{{ $entry ? 'clockOut' : 'clockIn' }}" wire:loading.attr="disabled" class="vxw-btn vxw-btn--primary" style="min-height:48px">{{ $entry ? 'Clock out' : 'Clock in' }}</button>
    </div>
</section>
