@php($greet = \App\Support\LoginGreeting::for())
@php($logo = filament()->getBrandLogo() ?: asset('images/vb-logo.svg'))
<div class="vx-login-experience" wire:ignore>
    <aside class="vx-login-art" aria-label="Vortex Ops">
        <div class="vx-wordmark"><img src="{{ $logo }}" alt="" width="42" height="42"> VORTEX <span>OPS</span></div>
        <h2>Power up<br>your next show.</h2>
        <p>Your shows, inventory, and payroll.<br>One connected workspace.</p>
        <div class="vx-feature-pills"><span>Shows</span><span>Inventory</span><span>Payroll</span></div>
        <div class="vx-capture-scene" aria-hidden="true">
            <div class="vx-orbit"></div>
            <div class="vx-trading-card vx-card-one"><img src="{{ $logo }}" alt="" width="48" height="48"></div>
            <div class="vx-trading-card vx-card-two"><img src="{{ $logo }}" alt="" width="48" height="48"></div>
            <div class="vx-trading-card vx-card-three"><img src="{{ $logo }}" alt="" width="48" height="48"></div>
            <div class="vx-capture-beam"></div>
            <div class="vx-ball-float"><div class="vx-capture-ball"><div class="vx-ball-top"></div><div class="vx-ball-bottom"></div><span class="vx-ball-button"><img src="{{ $logo }}" alt="" width="44" height="44"></span></div></div>
            <img class="vx-capture-buddy" src="{{ $greet['sprite'] }}" alt="" width="110" height="110">
            <div class="vx-platform"></div>
        </div>
    </aside>
    <div class="vx-login-greeting"><img src="{{ $greet['sprite'] }}" alt="" width="48" height="48"><span>{{ $greet['bubble'] }}<small>{{ $greet['caption'] }}</small></span></div>

    <span class="vx-capture-status" role="status" aria-live="polite"></span>
</div>
