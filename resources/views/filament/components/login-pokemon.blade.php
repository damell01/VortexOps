@php
    // One Pokémon greets you on the sign-in page; a different one each day.
    $mons = ['Pikachu', 'Charizard', 'Mew', 'Eevee', 'Snorlax', 'Psyduck', 'Meowth', 'Bulbasaur', 'Charmander', 'Squirtle', 'Gengar'];
    $mon = $mons[(int) now(\App\Support\BusinessTime::zone())->dayOfYear % count($mons)];
@endphp
<div class="vx-login-mon" aria-hidden="true">
    <span class="vx-login-bubble">Welcome back, Trainer!</span>
    <img src="/img/pokemon/{{ strtolower($mon) }}.gif" alt="" class="vx-login-sprite">
    <span class="vx-login-name">{{ $mon }} is on duty today</span>
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
@media (prefers-reduced-motion: reduce) { .vx-login-sprite, .vx-login-bubble { animation: none !important; } }
</style>
