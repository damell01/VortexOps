// Little Pokémon moments for a streamer's everyday actions. Livewire sends
// `vx-poke` with a kind; each plays once, briefly, and never under reduced motion.
//   clock_in   a Pokémon runs across the bottom of the screen: "Let's go!"
//   clock_out  a sleepy one pops up to say goodnight
//   item       a Poké Ball pops where you clicked and something peeks out
//   step       a Pokémon dashes along the top: "Step 2 of 3!"
//   ask        Psyduck, confused: "Psy…? Request sent!"
import '../css/poke-moments.css';

const calm = window.matchMedia('(prefers-reduced-motion: reduce)');
const SLEEPY = ['snorlax', 'jigglypuff', 'slowpoke', 'wooloo', 'umbreon', 'pachirisu', 'drowzee', 'munchlax'];
const SPEEDY = ['pikachu', 'jolteon', 'ponyta', 'scorbunny', 'riolu', 'shinx', 'zorua', 'growlithe', 'pawmi', 'eevee', 'torchic', 'chimchar', 'totodile', 'froakie', 'cyndaquil', 'arcanine', 'lucario', 'rowlet'];
let point = { x: innerWidth / 2, y: innerHeight / 2 };
let lastItem = 0;

function pool() {
    try { return JSON.parse(document.getElementById('vx-dex-pool')?.textContent || '[]'); } catch { return []; }
}
function pick(prefer = []) {
    const all = pool();
    const ok = prefer.filter((p) => all.includes(p));
    const from = ok.length ? ok : all;
    return from[Math.floor(Math.random() * from.length)] || 'pikachu';
}
function sprite(species, cls) {
    const img = document.createElement('img');
    img.className = cls;
    img.alt = '';
    // About one in fifty is shiny, like a catch.
    img.src = `/img/pokemon/${Math.random() < 0.02 ? 'shiny/' : ''}${species}.gif`;
    return img;
}
function layer(cls, ms) {
    const box = document.createElement('div');
    box.className = `vx-pm ${cls}`;
    box.setAttribute('aria-hidden', 'true');
    document.body.appendChild(box);
    setTimeout(() => box.remove(), ms);
    return box;
}
function bubble(text) {
    const b = document.createElement('span');
    b.className = 'vx-pm-say';
    b.textContent = text;
    return b;
}

const MOMENTS = {
    clock_in() {
        const box = layer('vx-pm-run', 3000);
        const runner = document.createElement('div');
        runner.className = 'vx-pm-runner';
        runner.append(bubble(["Let's go!", 'Time to shine!', 'Showtime!', 'Here we go!'][Math.floor(Math.random() * 4)]), sprite(pick(SPEEDY), 'vx-pm-mon'));
        box.appendChild(runner);
    },
    clock_out() {
        const box = layer('vx-pm-sleep', 3600);
        box.append(sprite(pick(SLEEPY), 'vx-pm-mon'), bubble('Nice work today! Rest up'));
        const z = document.createElement('i');
        z.className = 'vx-pm-z';
        z.textContent = 'z z Z';
        box.appendChild(z);
    },
    item() {
        if (Date.now() - lastItem < 350) return;
        lastItem = Date.now();
        const box = layer('vx-pm-item', 1300);
        box.style.left = `${point.x}px`;
        box.style.top = `${point.y}px`;
        const ball = document.createElement('span');
        ball.className = 'vx-pm-ball';
        const plus = document.createElement('b');
        plus.className = 'vx-pm-plus';
        plus.textContent = '+1';
        box.append(ball, sprite(pick(), 'vx-pm-mon'), plus);
    },
    step(detail) {
        const box = layer('vx-pm-dash', 2000);
        const runner = document.createElement('div');
        runner.className = 'vx-pm-runner';
        runner.append(bubble(detail?.step === 3 ? 'Last step!' : `Step ${detail?.step ?? 2} of 3!`), sprite(pick(SPEEDY), 'vx-pm-mon'));
        box.appendChild(runner);
    },
    ask() {
        const box = layer('vx-pm-ask', 2800);
        box.append(sprite('psyduck', 'vx-pm-mon'), bubble('Psy…? Request sent!'));
    },
};

window.addEventListener('vx-poke', (event) => {
    if (calm.matches) return;
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    MOMENTS[detail?.kind]?.(detail);
});
document.addEventListener('pointerdown', (e) => { point = { x: e.clientX, y: e.clientY }; }, { capture: true, passive: true });
window.vxPoke = (kind, extra = {}) => window.dispatchEvent(new CustomEvent('vx-poke', { detail: { kind, ...extra } }));
