@if ($events->isNotEmpty())
<aside aria-label="Recent inventory arrivals" class="rounded-xl border border-gray-700 p-3">
<h3 class="font-semibold">New inventory and restocks</h3>
<ul>
@foreach ($events as $event)
<li wire:key="arrival-{{ $event->id }}" class="py-2 text-sm">
<strong>{{ $event->kind === 'restocked' ? 'Restocked' : 'New arrival' }}</strong> — {{ $event->name }}
</li>
@endforeach
</ul>
</aside>
@endif
