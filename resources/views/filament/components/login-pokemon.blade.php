@php
    // A Pokémon greets you on the sign-in page: the time of day picks the crew
    // (and what they say), the day of the year picks who is on duty.
    $greet = \App\Support\LoginGreeting::for();
@endphp
<div class="vx-login-mon @if ($greet['sleepy']) is-sleepy @endif" aria-hidden="true" data-period="{{ $greet['period'] }}">
    <span class="vx-login-bubble">{{ $greet['bubble'] }}</span>
    <span class="vx-login-stage">
        <img src="{{ $greet['sprite'] }}" alt="" class="vx-login-sprite">
        @if ($greet['sleepy'])
            <span class="vx-login-z"><i>z</i><i>z</i><i>Z</i></span>
        @endif
    </span>
    <span class="vx-login-name">{{ $greet['caption'] }}</span>
</div>
<style>
.vx-login-mon { display: grid; justify-items: center; gap: 4px; margin: -6px 0 10px; }
.vx-login-sprite { height: 72px; width: auto; image-rendering: pixelated; filter: drop-shadow(0 6px 8px rgb(40 20 80 / .25)); animation: vx-login-float 3.4s ease-in-out infinite; transform-origin: bottom center; }
.vx-login-mon:hover .vx-login-sprite { animation: vx-login-hop .5s ease-out; }
.vx-login-bubble { position: relative; padding: 6px 12px; border-radius: 12px; background: #f3edff; color: #5b21b6; font-size: 13px; font-weight: 650; animation: vx-login-pop .45s cubic-bezier(.3,1.5,.6,1) .3s backwards; }
.vx-login-bubble::after { content: ''; position: absolute; left: 50%; bottom: -6px; width: 12px; height: 12px; background: inherit; transform: translateX(-50%) rotate(45deg); border-radius: 2px; }
.dark .vx-login-bubble { background: #2e1065; color: #e9d5ff; }
.vx-login-name { font-size: 11.5px; color: #8a8a99; letter-spacing: .02em; }
@keyframes vx-login-float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
@keyframes vx-login-hop { 0%, 100% { transform: translateY(0); } 35% { transform: translateY(-10px) scale(1.04); } 60% { transform: translateY(0) scale(1.03, .97); } }
@keyframes vx-login-pop { from { opacity: 0; transform: translateY(6px) scale(.8); } }
.vx-login-stage { position: relative; display: inline-block; }
/* Late at night the greeter is half asleep: slower breathing and drifting z's. */
.vx-login-mon.is-sleepy .vx-login-sprite { animation: vx-login-doze 4.8s ease-in-out infinite; }
.vx-login-z { position: absolute; top: 2px; right: -14px; pointer-events: none; }
.vx-login-z i { position: absolute; font-style: normal; font-weight: 700; color: #8b5cf6; opacity: 0; animation: vx-login-zz 3.6s ease-out infinite; }
.vx-login-z i:nth-child(2) { font-size: 13px; animation-delay: 1.2s; }
.vx-login-z i:nth-child(1) { font-size: 10px; }
.vx-login-z i:nth-child(3) { font-size: 16px; animation-delay: 2.4s; }
@keyframes vx-login-doze { 0%, 100% { transform: scale(1, 1); } 50% { transform: scale(1.03, .97); } }
@keyframes vx-login-zz { 0% { opacity: 0; transform: translate(0, 0); } 20% { opacity: .9; } 100% { opacity: 0; transform: translate(12px, -26px); } }
@media (prefers-reduced-motion: reduce) { .vx-login-sprite, .vx-login-bubble, .vx-login-z i { animation: none !important; } .vx-login-z i { opacity: .7; } .vx-login-z i:nth-child(2) { transform: translate(6px, -10px); } .vx-login-z i:nth-child(3) { transform: translate(12px, -22px); } }
</style>
