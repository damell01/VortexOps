@php($greet = \App\Support\LoginGreeting::for())
{{-- Sits at the top of the sign-in card; the slab wall behind it is login-wall. --}}
<div class="vx-login-experience vx-login-wall" wire:ignore>
    <div class="vx-login-greeting"><img src="{{ $greet['sprite'] }}" alt="" width="48" height="48"><span>{{ $greet['bubble'] }}<small>{{ $greet['caption'] }}</small></span></div>
    <span class="vx-capture-status" role="status" aria-live="polite"></span>
</div>
