<x-filament-panels::page>
<div class="mx-auto max-w-2xl space-y-5 rounded-xl border border-gray-200 p-5 dark:border-gray-700">
<h2 class="text-lg font-bold">Inventory update popup</h2>
<p class="text-sm text-gray-500">Show inventory changes when selected team members sign in. Streamers are the recommended starting audience. This feature is off until enabled.</p>
<label class="flex items-center gap-3"><input type="checkbox" wire:model="enabled"> Enable inventory login popups</label>
<fieldset class="space-y-2">
<legend class="font-semibold">Who should see the popup? (streamer rollout)</legend>
@foreach (['streamer' => 'Streamers'] as $key => $name)
<label class="flex items-center gap-2"><input type="checkbox" wire:model="roles" value="{{ $key }}"> {{ $name }}</label>
@endforeach
</fieldset>
<div class="space-y-2">
<label class="block font-semibold" for="popup-streamer-search">Select streamers to receive the popup</label>
<p class="text-sm text-gray-500">For a limited test, uncheck Streamers above and select only your test accounts below. Selected streamers are also included when the general Streamers audience is enabled.</p>
<input id="popup-streamer-search" type="search" wire:model.live.debounce.300ms="streamerSearch" class="w-full rounded-lg border border-gray-300 p-3 text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Find streamer by name or email...">
<div class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-gray-700">
@forelse($this->streamers as $streamer)
<label wire:key="popup-streamer-{{ $streamer['id'] }}" class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-gray-100 dark:hover:bg-gray-800">
<input type="checkbox" wire:model="selectedStreamers" value="{{ $streamer['id'] }}">
<span><span class="block font-medium">{{ $streamer['name'] }}</span><span class="block text-xs text-gray-500">{{ $streamer['email'] }}</span></span>
</label>
@empty
<p class="p-3 text-sm text-gray-500">No matching streamer accounts.</p>
@endforelse
</div>
<p class="text-sm text-gray-500">{{ count($selectedStreamers) }} streamer(s) selected</p>
@error('selectedStreamers') <p class="text-sm text-red-500">{{ $message }}</p> @enderror
</div>
<x-filament::button wire:click="save">Save popup settings</x-filament::button>
</div>
</x-filament-panels::page>
