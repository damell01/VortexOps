@php($entryLogo = filament()->getBrandLogo() ?: asset('images/vb-logo.svg'))
<div class="vx-workspace-entry" data-vx-workspace-entry hidden role="status" aria-live="polite" aria-label="Opening your workspace">
    <div class="vx-entry-content">
        <div class="vx-entry-scene" aria-hidden="true">
            <div class="vx-entry-ring"></div>
            <div class="vx-trading-card vx-card-one"><img src="{{ $entryLogo }}" alt="" width="48" height="48"></div>
            <div class="vx-trading-card vx-card-two"><img src="{{ $entryLogo }}" alt="" width="48" height="48"></div>
            <div class="vx-trading-card vx-card-three"><img src="{{ $entryLogo }}" alt="" width="48" height="48"></div>
            <div class="vx-ball-float"><div class="vx-capture-ball"><div class="vx-ball-top"></div><div class="vx-ball-bottom"></div><span class="vx-ball-button"><img src="{{ $entryLogo }}" alt="" width="44" height="44"></span></div></div>
            <div class="vx-entry-light"></div>
        </div>
        <p class="vx-entry-title" data-vx-entry-status>Opening your workspace…</p>
        <p class="vx-entry-caption">Getting your team’s workspace ready.</p>
        <span class="vx-entry-loader" aria-hidden="true"></span>
        <a class="vx-entry-continue" data-vx-entry-continue hidden>Taking longer? Open your workspace</a>
    </div>
</div>
