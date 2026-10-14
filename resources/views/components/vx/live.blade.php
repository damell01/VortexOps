{{-- A pulsing "Live now" chip for a show that is streaming right now (Show::isLive()). --}}
<span {{ $attributes->class('vx-live') }}><span class="vx-live-dot" aria-hidden="true"></span>{{ $slot->isEmpty() ? 'Live now' : $slot }}</span>
