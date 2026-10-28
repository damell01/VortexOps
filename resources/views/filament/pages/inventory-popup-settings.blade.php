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
<div class="space-y-1"><label class="block font-semibold" for="popup-user-ids">Additional streamer user IDs (optional)</label>
<input id="popup-user-ids" wire:model="userIds" class="w-full rounded border p-2 text-gray-900" placeholder="e.g. 12, 18">
@error('userIds') <p class="text-sm text-red-500">{{ $message }}</p> @enderror</div>
<x-filament::button wire:click="save">Save popup settings</x-filament::button>
</div>
</x-filament-panels::page>
