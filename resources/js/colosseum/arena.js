// Vortex Colosseum: the top three walk into a Vortex-branded arena, #2 and #3
// challenge #1, the champion knocks both down (on screen, never off it), takes
// the crown, and the floor turns into the podium. About eight seconds, scripted
// rather than simulated, so every run reads the same and Skip can jump to the
// end state exactly.
import * as THREE from 'three';
import { buildStage, animateStage, cheer, PODIUM, GATES } from './stage.js';
import { Effects, buildCrown } from './effects.js';
import { Actor, loadTemplate } from './actor.js';
import { ROSTER, fighterFor } from './roster.js';
import { Sound } from './sound.js';
import { Post } from './post.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const END = 8;
const clamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
const ease = (k) => k * k * (3 - 2 * k);
const easeOut = (k) => 1 - (1 - k) ** 3;
const span = (t, a, b) => clamp((t - a) / (b - a));
const V = (x, y, z) => new THREE.Vector3(x, y, z);
// A camera mark; 'hN' in it means N × the champion's height (see ref()).
const M = (x, y, z) => ({ x, y, z });

// Where each place stands during the fight.
const BATTLE = { 1: V(0, 0, -0.7), 2: V(-2.2, 0, 0.9), 3: V(2.2, 0, 0.9) };
const KNOCKED = { 2: V(-3.4, 0, 1.25), 3: V(3.4, 0, 1.25) };
const gateSpot = (rank) => V(Math.cos(GATES[rank]) * 6.1, 0, Math.sin(GATES[rank]) * 6.1);
const podiumSpot = (rank) => V(PODIUM[rank].x, PODIUM[rank].h, 0.3);

// Camera direction, shot by shot: [time, position, look-at, field of view].
// Interpolated as a smooth spline so moves ease through each mark like a
// dolly/crane rather than snapping between them.
const CAMERA = [
    [0, M(0, 9.5, 17.5), M(0, 0.4, -2.2), 30],      // establishing crane, high over the stands
    [1.0, M(5.8, 3.4, 9.6), M(0, 1, -0.6), 36],     // sweep round as the gates light
    [2.0, M(1.2, 2.1, 7.9), M(0, 1, 0), 40],        // fighters land
    [2.55, M(0.8, 0.75, 3.4), M(0, 'h0.9', -0.7), 33], // low hero angle on the champion
    [3.05, M(1.7, 1.3, 4.3), M(0, 'h0.8', -0.5), 36],  // wind-up
    [3.6, M(6.6, 2.3, 3.4), M(0, 1, 0.3), 42],      // wide side as the attack crosses
    [4.15, M(3.4, 1.3, 5.6), M(-0.6, 0.7, 1), 40],  // follow the knockdowns
    [4.9, M(1.2, 'h1.0', 4.4), M(0, 'h0.95', -0.7), 34], // champion rises to celebrate
    [5.65, M(0.1, 'h1.3', 2.8), M(0, 'h1.15', -0.7), 30], // push in as the crown lands
    [6.4, M(-1.5, 2.6, 7.2), M(0, 1.3, 0), 36],     // pull away as podiums rise
    [7.7, M(0, 3.2, 9.6), M(0, 1.35, 0.2), 40],     // final framing (matches the settled view)
];

// 'hN' in a mark means N × the champion's height, so close-ups frame a
// Pikachu and a Charizard alike.
let champHeight = 1.4;
const ref = (v) => (typeof v === 'string' ? parseFloat(v.slice(1)) * champHeight : v);
const mark = (v) => new THREE.Vector3(ref(v.x), ref(v.y), ref(v.z));

const cat = (p0, p1, p2, p3, u) => {
    const u2 = u * u, u3 = u2 * u;
    return 0.5 * ((2 * p1) + (-p0 + p2) * u + (2 * p0 - 5 * p1 + 4 * p2 - p3) * u2 + (-p0 + 3 * p1 - 3 * p2 + p3) * u3);
};
const catV = (a, b, c, d, u) => {
    [a, b, c, d] = [a, b, c, d].map(mark);
    return V(cat(a.x, b.x, c.x, d.x, u), cat(a.y, b.y, c.y, d.y, u), cat(a.z, b.z, c.z, d.z, u));
};

function cameraAt(t) {
    const n = CAMERA.length;
    if (t >= CAMERA[n - 1][0]) return [mark(CAMERA[n - 1][1]), mark(CAMERA[n - 1][2]), CAMERA[n - 1][3]];
    let i = 0;
    while (i < n - 2 && t > CAMERA[i + 1][0]) i++;
    const k = (j) => CAMERA[Math.max(0, Math.min(n - 1, j))];
    const u = ease(span(t, CAMERA[i][0], CAMERA[i + 1][0]));
    return [
        catV(k(i - 1)[1], k(i)[1], k(i + 1)[1], k(i + 2)[1], u),
        catV(k(i - 1)[2], k(i)[2], k(i + 1)[2], k(i + 2)[2], u),
        k(i)[3] + (k(i + 1)[3] - k(i)[3]) * u,
    ];
}

// Playback speed by moment. Full: real time, with slow motion through the
// hit. Quick (about 3 s): skips the entrance, stays near real time for the
// attack and slow-mo hit, then races through the crowning and podiums.
function rateAt(t, mode) {
    if (mode === 'quick') {
        if (t < 2.9) return 3.2;
        if (t < 3.82) return 1.25;
        if (t < 4.2) return 0.5;
        if (t < 5.05) return 2.4;
        if (t < 6.05) return 3.2;
        return 3.4;
    }
    if (t >= 3.82 && t < 4.2) return 0.32;
    return 1;
}

const yawTo = (from, to) => Math.atan2(to.x - from.x, to.z - from.z);
const lerpAngle = (a, b, k) => a + (((b - a + Math.PI * 3) % (Math.PI * 2)) - Math.PI) * k;

export class Arena {
    /**
     * @param {HTMLElement} host   element the canvas goes in (labels are added beside it)
     * @param {Array} lineup       [{ rank, name, value, buddy, seed }] for places 1–3
     */
    constructor(host, lineup, { base = '/models/colosseum/', version = '', onLabels, onState, compact = false } = {}) {
        this.host = host;
        this.base = base;
        this.version = version;
        this.onLabels = onLabels;
        this.onState = onState;
        // Dashboards use the light path everywhere: no bloom, no shadows, 1× pixels.
        this.compact = compact;
        this.lite = compact || matchMedia('(max-width: 768px)').matches || (navigator.hardwareConcurrency || 8) <= 4;
        this.reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

        this.renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
        // Sharp while it moves; the resting frame is redrawn even sharper (rest()).
        this.ratio = Math.min(devicePixelRatio || 1, this.lite ? 1.5 : 2);
        this.renderer.setPixelRatio(this.ratio);
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.05;
        this.renderer.shadowMap.enabled = !this.lite;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.renderer.domElement.className = 'vxa-canvas';
        host.prepend(this.renderer.domElement);
        // If the browser drops the 3D canvas (Edge and laptops switching graphics
        // do this), rebuild once it is restored; if it never is, the page falls
        // back to the regular top-three cards.
        this.onLost = (e) => { e.preventDefault(); this.lost = true; this.onState?.('lost'); };
        this.onRestored = () => { this.lost = false; this.resize(); this.loadedKey = null; this.load(this.lineup).then(() => this.settle()); };
        this.renderer.domElement.addEventListener('webglcontextlost', this.onLost);
        this.renderer.domElement.addEventListener('webglcontextrestored', this.onRestored);

        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(40, 16 / 9, 0.1, 80);
        this.set = buildStage(this.scene, { lite: this.lite });
        // Soft studio reflections on stone, gold and the fighters.
        const pmrem = new THREE.PMREMGenerator(this.renderer);
        this.scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
        this.scene.environmentIntensity = 0.24;
        pmrem.dispose();
        this.post = this.lite ? null : new Post(this.renderer, this.scene, this.camera);
        this.fx = new Effects(this.scene, this.set.glow, { lite: this.lite });
        this.crown = buildCrown();
        this.scene.add(this.crown);
        this.sound = new Sound();

        this.t = 0;
        this.clock = new THREE.Timer();
        this.state = 'idle'; // idle | loading | playing | settled
        this.actors = {};
        this.events = [];
        this.shake = 0;
        this.visible = true;
        this.lastFrame = 0;

        this.resize = this.resize.bind(this);
        this.loop = this.loop.bind(this);
        this.ro = new ResizeObserver(this.resize);
        this.ro.observe(host);
        // A settled podium draws for a moment, then stops for good (the last
        // frame stays on screen). Nothing here wakes it while the page scrolls:
        // only a replay, a resize or new standings do.
        // Back on screen with a cleared canvas (it was resized while away): redraw.
        this.io = new IntersectionObserver(([e]) => { this.visible = e.isIntersecting; if (e.isIntersecting && this.needsDraw) this.wake(); });
        this.io.observe(host);
        this.onVis = () => this.clock.reset();
        document.addEventListener('visibilitychange', this.onVis);
        this.resize();
        this.lineup = lineup;
    }

    resize() {
        this.wake?.();
        const w = this.host.clientWidth || 1, h = this.host.clientHeight || 1;
        this.sized = [w, h];
        this.renderer.setSize(w, h, false);
        this.post?.setSize(w, h, this.renderer.getPixelRatio());
        this.needsDraw = true;
        this.camera.aspect = w / h;
        this.camera.updateProjectionMatrix();
        // Narrow screens pull back to fit; the wide dashboard strip moves in closer.
        this.zoom = Math.max(1, 1.5 / this.camera.aspect);
        this.final = this.finalShot(this.camera.aspect);
    }

    async load(lineup) {
        const picks = lineup.slice(0, 3).map((row) => ({ ...row, ...fighterFor(row.buddy, row.seed) }));
        // Already loaded (prepare() ran while the page opened): nothing to fetch.
        const key = JSON.stringify(picks.map((p) => [p.rank, p.slug, p.id]));
        if (key === this.loadedKey && Object.keys(this.actors).length) return;
        if (this.loadingKey === key) return this.loadingPromise;
        this.loadingKey = key;
        this.loadingPromise = this.build(picks, key);
        return this.loadingPromise;
    }

    /** Load all fighters and warm up the scene without drawing anything. */
    prepare() { return this.load(this.lineup); }

    async build(picks, key) {
        if (this.state === 'idle') this.setState('loading');
        const templates = await Promise.all(picks.map((p) => loadTemplate(p.slug, this.base, this.version)));
        for (const a of Object.values(this.actors)) { a.dispose(); a.blob?.removeFromParent(); }
        this.actors = {};
        picks.forEach((p, i) => {
            const actor = new Actor(p.slug, templates[i], { lite: this.lite, anisotropy: this.renderer.capabilities.getMaxAnisotropy() });
            actor.row = p;
            actor.blob = new THREE.Mesh(new THREE.PlaneGeometry(1, 1), new THREE.MeshBasicMaterial({ map: this.set.blob, transparent: true, depthWrite: false }));
            actor.blob.rotation.x = -Math.PI / 2;
            actor.blob.renderOrder = 1;
            this.scene.add(actor.blob);
            this.actors[p.rank] = actor;
            this.scene.add(actor.root);
        });
        this.picks = picks;
        champHeight = this.actors[1]?.cfg.height || 1.4;
        // Upload every texture and shader now, so no fighter appears untextured mid-fight.
        this.scene.traverse((o) => { for (const m of [].concat(o.material || [])) for (const k of ['map', 'emissiveMap', 'normalMap']) if (m[k]) this.renderer.initTexture(m[k]); });
        await this.renderer.compileAsync(this.scene, this.camera).catch(() => {});
        this.loadedKey = key;
        this.loadingKey = null;
    }

    setState(s) { this.state = s; this.onState?.(s); }

    /** Run the battle from the top: mode 'full' (~8.5 s) or 'quick' (~3 s). */
    async play(mode = 'full') {
        this.sound.unlock();
        await this.load(this.lineup);
        this.mode = mode;
        this.normalRes();
        this.reset();
        if (mode === 'quick') {
            // Start with everyone already squared up in the lit arena.
            this.t = 2.06;
            this.events = this.events.filter(([at]) => at >= 2.06);
            for (const [rank, a] of Object.entries(this.actors)) a.root.position.copy(BATTLE[rank]);
        }
        this.setState('playing');
        this.start();
    }

    /** Show the finished podium without the fight (dashboards, reduced motion, Skip). */
    async showPodium() {
        await this.load(this.lineup);
        this.settle();
        this.start();
    }

    start() {
        if (!this.running) {
            this.running = true;
            this.clock.reset();
            requestAnimationFrame(this.loop);
        }
    }

    /**
     * The podium people look at is a still: before going to sleep, draw that
     * one frame again at up to 3x resolution, so the resting stage is near 4K.
     * One heavy frame, then nothing; any wake goes back to the normal ratio.
     */
    restFrame() {
        if (this.lost || !this.visible) return;
        const hi = Math.min((devicePixelRatio || 1) * (this.lite ? 1.5 : 2), this.lite ? 2.5 : 3);
        if (hi <= this.ratio) return;
        // Supersampled already: skip multisampling, which at this size would
        // eat enough graphics memory to make a laptop drop the canvas.
        this.post?.setSamples(0);
        this.setRatio(hi);
        if (this.post) this.post.render(this.wall || 0, { bloom: 0.32, fringe: 0 });
        else this.renderer.render(this.scene, this.camera);
        this.needsDraw = false;
        this.hiRes = true;
    }

    setRatio(r) {
        const w = this.host.clientWidth || 1, h = this.host.clientHeight || 1;
        this.renderer.setPixelRatio(r);
        this.renderer.setSize(w, h, false);
        this.post?.setSize(w, h, r);
        this.needsDraw = true;
    }

    /** Draw again for a while (settled scenes sleep to keep the page light). */
    /** Back from the resting 3x frame to the normal ratio (and multisampling). */
    normalRes() {
        if (!this.hiRes) return;
        this.hiRes = false;
        this.post?.setSamples(4);
        this.setRatio(this.ratio);
    }

    wake() {
        this.normalRes();
        this.settledAt = performance.now();
        if (this.state !== 'idle' && this.state !== 'loading') this.start();
    }

    /** New standings: swap fighters straight onto the podium, no replay. */
    async setLineup(lineup) {
        this.lineup = lineup;
        if (this.state === 'idle') return;
        await this.load(lineup);
        this.settle();
    }

    reset() {
        this.t = 0;
        this.fx.clear();
        this.crown.visible = false;
        this.events = this.buildEvents();
        for (const [rank, a] of Object.entries(this.actors)) {
            a.root.position.copy(gateSpot(+rank));
            a.root.rotation.set(0, yawTo(gateSpot(+rank), V(0, 0, 0)), 0);
            a.root.scale.setScalar(1);
            a.rest_();
        }
        this.mode = this.mode || 'full';
        for (const p of Object.values(this.set.podiums)) p.position.y = -p.userData.h - 0.05;
    }

    skip() {
        if (this.state === 'playing') this.settle();
    }

    settle() {
        this.t = END;
        this.fx.clear();
        this.events = [];
        for (const p of Object.values(this.set.podiums)) p.position.y = 0;
        for (const [rank, a] of Object.entries(this.actors)) {
            a.root.position.copy(podiumSpot(+rank)).add(V(0, a.cfg.hover || 0, 0));
            a.root.rotation.set(0, yawTo(podiumSpot(+rank), V(0, 0, 12)) * 0.5, 0);
            a.root.scale.setScalar(1);
            Object.assign(a.pose, { lean: 0, armsUp: 0, wings: 0, wingsOpen: 0, headPitch: 0 });
            a.rest_();
        }
        this.crown.visible = !!this.actors[1];
        this.applyLights(END);
        this.setState('settled');
        this.settledAt = performance.now();
        this.start();
    }

    /** One-off moments: sounds, clips, effects. */
    buildEvents() {
        const A = this.actors, champ = A[1], cfg = champ?.cfg;
        const challengers = [A[2], A[3]].filter(Boolean);
        const mouth = () => V(champ.root.position.x, champ.root.position.y + cfg.height * cfg.mouth, champ.root.position.z).add(V(0, 0, 0.35).applyAxisAngle(V(0, 1, 0), champ.root.rotation.y));
        const chest = (a) => V(a.root.position.x, a.root.position.y + a.cfg.height * 0.55, a.root.position.z);
        const impact = () => {
            this.sound.impact();
            cheer(this.set, this.wall ?? 0);
            // With Reduce Motion on, the battle still plays but without the shake and flash.
            this.shake = this.reduced ? 0 : 0.32;
            this.punch = this.reduced ? 0 : 1;
            this.flash(cfg.color, 2.2);
            for (const a of challengers) {
                const at = chest(a);
                this.fx.glowAt(at, cfg.color, { from: 0.4, to: 2.4, life: 0.45 });
                this.fx.burst(at, { color: cfg.color, count: 26, speed: 3, life: 0.7 });
                this.fx.burst(at, { color: 0xffffff, count: 10, speed: 4, life: 0.4, size: 0.08 });
                this.fx.ring(a.root.position, cfg.color, { radius: 2 });
                a.play('hit', { fade: 0.08 });
                if (a.cfg.hitClip) a.play(a.cfg.hitClip.name, { timeScale: a.cfg.hitClip.timeScale, fade: 0.1 });
            }
        };
        return [
            [0.15, () => this.sound.hum()],
            [1.0, () => challengers.concat(champ).forEach((a) => a?.play('walk', { loop: true }))],
            [2.05, () => {
                this.sound.land();
                for (const a of Object.values(A)) { this.fx.dust(a.root.position, 1.1); a.rest_(); }
            }],
            [2.2, () => champ?.play('battle', { loop: true }) || champ?.play('roar')],
            [2.9, () => { this.sound.charge(); this.fx.glowAt(mouth(), cfg.color, { from: 0.1, to: 1.1, life: 0.55, opacity: 0.9 }); }],
            [3.05, () => {
                if (cfg.attackClip) champ.play(cfg.attackClip.name, { timeScale: cfg.attackClip.timeScale, fade: 0.12 });
            }],
            [3.3, () => {
                const targets = challengers.map(chest);
                this.flash(cfg.color, 1.4);
                if (cfg.attack === 'bolt') {
                    targets.forEach((to) => this.fx.bolt(mouth(), to, cfg.color, 0.6));
                    this.fx.glowAt(mouth(), cfg.color, { from: 0.6, to: 1.8, life: 0.6 });
                    this.sound.zap();
                } else if (cfg.attack === 'stream') {
                    this.fx.stream(mouth(), targets, cfg.color, 0.6);
                    this.sound.whoosh();
                } else {
                    targets.forEach((to) => this.fx.orb(mouth(), to, cfg.color, 0.5));
                    this.sound.whoosh();
                }
            }],
            [3.88, impact],
            [4.1, () => challengers.forEach((a) => a.play('down_start', { fade: 0.1 }))],
            [4.55, () => challengers.forEach((a) => { this.fx.dust(a.root.position, 1.3); a.play('down_loop', { loop: true, fade: 0.1 }); })],
            [5.0, () => champ?.play('roar') || champ?.play('glad')],
            [5.2, () => { this.crown.visible = true; this.sound.sparkle(); cheer(this.set, this.wall ?? 0, true); }],
            [5.25, () => challengers.forEach((a) => a.play('down_end', { fade: 0.15 }))],
            [5.6, () => {
                this.fx.burst(champ.headTop().add(V(0, 0.3, 0)), { color: 0xffd24a, count: 34, speed: 1.6, up: 1.4, gravity: -1.8, size: 0.12, life: 1.4 });
                this.sound.fanfare();
            }],
            [6.0, () => Object.values(A).forEach((a) => a.rest_())],
            [6.2, () => this.sound.rumble()],
            [7.55, () => { this.sound.land(); Object.entries(A).forEach(([r, a]) => this.fx.dust(podiumSpot(+r), 0.7)); }],
        ];
    }

    flash(color, power) {
        this.set.fxLight.color.setHex(color);
        this.set.fxLight.position.copy(this.actors[1]?.root.position || V(0, 0, 0)).add(V(0, 1.6, 1));
        this.flashPower = power * 40;
    }

    applyLights(t) {
        const on = ease(span(t, 0, 1.1)), soft = 1 - span(t, 6.2, 7.6) * 0.3;
        this.set.gateLights.forEach((l, i) => { l.intensity = 9 * ease(span(t, 0.1 + i * 0.12, 0.7 + i * 0.12)) * (1 - span(t, 6, 7.5) * 0.6); });
        this.set.spots.forEach((s) => { s.intensity = 70 * on * soft; });
        this.set.champLight.intensity = 55 * ease(span(t, 5, 6.2));
        this.set.trim.material.emissiveIntensity = 0.4 + 1.4 * on;
    }

    /** Where each fighter is at time t. */
    choreograph(t) {
        const A = this.actors;
        for (const [rankKey, a] of Object.entries(A)) {
            const rank = +rankKey, cfg = a.cfg, champ = rank === 1, flier = cfg.style !== 'hop';
            const hover = cfg.hover || 0;
            const P = a.pose, pos = a.root.position, rot = a.root.rotation;
            let x, y = 0, z, yaw, tipX = 0, tipZ = 0;
            Object.assign(P, { lean: 0, armsUp: 0, wings: flier ? 0.15 : 0, wingsOpen: 0, headPitch: 0, tail: 1 });
            a.root.scale.setScalar(1);

            const battle = BATTLE[rank], start = gateSpot(rank);
            const face = champ ? V(0, 0, 1.4) : BATTLE[1];

            if (t < 2.1) {
                // Entrance: hop, glide or float in from their gate and land.
                const k = ease(span(t, 1, 2.05));
                x = start.x + (battle.x - start.x) * k;
                z = start.z + (battle.z - start.z) * k;
                y = cfg.style === 'hop' ? Math.abs(Math.sin(k * Math.PI * 3)) * 0.38 * (1 - k * 0.4) : Math.sin(k * Math.PI) * 1.3;
                if (flier && k > 0 && k < 1) P.wings = 1;
                yaw = lerpAngle(yawTo(start, battle), yawTo(battle, face), span(t, 1.7, 2.05));
            } else if (t < 3.9 || champ) {
                x = battle.x; z = battle.z;
                yaw = yawTo(battle, face);
                if (!champ) {
                    // Anticipation: crouch back, then step in.
                    const crouch = Math.sin(Math.PI * span(t, 2.2, 2.75));
                    a.root.scale.set(1 + crouch * 0.05, 1 - crouch * 0.09, 1 + crouch * 0.05);
                    P.lean = -0.25 * crouch + 0.2 * span(t, 2.75, 3.1);
                    const step = ease(span(t, 2.75, 3.1)) * 0.35;
                    x += (BATTLE[1].x - battle.x) * step * 0.25;
                    z += (BATTLE[1].z - battle.z) * step * 0.25;
                    P.armsUp = 0.3 * span(t, 2.75, 3.1);
                } else {
                    // Champion: rise up, wind up, lunge into the attack, celebrate.
                    const lunge = Math.sin(Math.PI * span(t, 3.0, 3.75)) * 0.4;
                    z += lunge;
                    P.lean = 0.35 * Math.sin(Math.PI * span(t, 2.9, 3.8));
                    P.headPitch = 0.25 * Math.sin(Math.PI * span(t, 3.1, 3.8));
                    P.armsUp = 0.8 * Math.sin(Math.PI * span(t, 2.9, 3.8));
                    P.wingsOpen = flier ? Math.sin(Math.PI * span(t, 2.9, 3.9)) : 0;
                    if (t >= 5 && t < 6.1) {
                        // Victory: each body type celebrates its own way.
                        const v = span(t, 5, 6.05);
                        if (cfg.style === 'hop') { y = Math.abs(Math.sin(v * Math.PI * 2)) * 0.45; P.armsUp = 1; P.tail = 1.8; }
                        else if (cfg.style === 'wings') { y = Math.sin(v * Math.PI) * 0.55; P.wings = 1; P.wingsOpen = 1; P.headPitch = -0.3; }
                        else { y = Math.sin(v * Math.PI) * 0.4; yaw += v * Math.PI * 2; P.armsUp = 0.7; }
                    }
                }
            } else {
                // Challengers: knocked back and down, then back on their feet.
                const k = easeOut(span(t, 3.88, 4.55)), from = BATTLE[rank], to = KNOCKED[rank];
                x = from.x + (to.x - from.x) * k;
                z = from.z + (to.z - from.z) * k;
                yaw = yawTo(from, BATTLE[1]);
                const down = ease(span(t, 3.95, 4.55)) * (1 - ease(span(t, 5.25, 5.95)));
                const side = rank === 2 ? 1 : -1;
                if (!a.has('down_start')) {
                    if (cfg.style === 'hop') { tipX = -1.35 * down; y = Math.sin(Math.PI * span(t, 3.9, 4.5)) * 0.35 + Math.abs(Math.sin(Math.PI * span(t, 5.3, 5.8))) * 0.3; }
                    else if (cfg.style === 'wings') { tipX = -0.95 * down + 0.35 * Math.sin(Math.PI * span(t, 4.5, 5.0)); P.wings = 0.6 + 0.4 * span(t, 4.5, 5.2); P.wingsOpen = down; }
                    else { tipZ = side * 1.3 * down; yaw += Math.sin(Math.PI * span(t, 3.9, 4.6)) * 1.4; }
                }
                P.headPitch = -0.3 * down;
                if (t < 4.0) a.root.scale.set(1.06, 0.92, 1.06);
            }

            // Finale: everyone moves to their podium, facing the stands.
            if (t >= 6.05) {
                const k = ease(span(t, 6.15, 7.55)), from = champ ? BATTLE[1] : KNOCKED[rank], to = podiumSpot(rank);
                x = from.x + (to.x - from.x) * k;
                z = from.z + (to.z - from.z) * k;
                const baseY = to.y * ease(span(t, 7.0, 7.55));
                y = baseY + Math.sin(Math.PI * k) * (cfg.style === 'hop' ? 1.2 : 1.5);
                if (flier && k > 0 && k < 1) P.wings = 1;
                yaw = lerpAngle(yawTo(from, to), yawTo(to, V(0, 0, 12)) * 0.5, span(t, 7.0, 7.6));
                tipX = tipZ = 0;
            }

            pos.set(x, y + hover, z);
            rot.set(tipX, yaw, tipZ, 'YXZ');
        }

        // Podiums rise out of the floor for the finale.
        for (const [rank, p] of Object.entries(this.set.podiums)) {
            const k = easeOut(span(t, 6.2 + (rank - 1) * 0.12, 7.35 + (rank - 1) * 0.12));
            p.position.y = (-p.userData.h - 0.05) * (1 - k);
        }
    }

    loop(now) {
        if (!this.running) return;
        // Asleep: a settled podium nobody is pointing at needs no more frames.
        if (this.state === 'settled' && now - this.settledAt > 1500) { this.running = false; this.restFrame(); return; }
        requestAnimationFrame(this.loop);
        if (!this.visible || document.hidden || this.lost) return;
        // A resize the observer missed (scaling change, scrollbar) would leave
        // the scene drawn into part of the canvas: check before every frame.
        if (this.sized?.[0] !== this.host.clientWidth || this.sized?.[1] !== this.host.clientHeight) this.resize();
        // Settled scenes only need a gentle idle: 30 fps is plenty.
        if (this.state === 'settled' && now - this.lastFrame < 32) return;
        this.lastFrame = now;
        this.clock.update(now);
        const dt = Math.min(0.05, this.clock.getDelta());
        const wall = this.clock.getElapsed();
        this.wall = wall;

        let rate = 1;
        if (this.state === 'playing') {
            rate = rateAt(this.t, this.mode);
            this.t += dt * rate;
            while (this.events.length && this.events[0][0] <= this.t) {
                const [, fn] = this.events.shift();
                try { fn(); } catch (e) { console.warn('[colosseum]', e); }
            }
            this.choreograph(this.t);
            this.applyLights(this.t);
            if (this.t >= END) { this.settle(); }
        }

        for (const a of Object.values(this.actors)) {
            a.update(dt * rate, wall);
            // Contact shadow: shrinks and fades as the fighter leaves the ground.
            const air = Math.max(0, a.root.position.y - (this.podiumTop(a) || 0));
            const r = a.cfg.height * 0.75 * (1 - Math.min(0.6, air * 0.35));
            a.blob.scale.set(r, r, 1);
            a.blob.position.set(a.root.position.x, (this.podiumTop(a) || 0) + 0.015, a.root.position.z);
            a.blob.material.opacity = 1 - Math.min(0.8, air * 0.5);
        }
        this.fx.update(dt * rate);
        animateStage(this.set, wall);

        // Crown floats over the champion's head.
        const champ = this.actors[1];
        if (champ && this.crown.visible) {
            const drop = this.state === 'playing' ? 1 - easeOut(span(this.t, 5.2, 5.7)) : 0;
            const s = Math.max(0.75, Math.min(1.5, champ.cfg.height * 0.8));
            this.crown.scale.setScalar(s);
            // Sit on the head itself (its bone), not above wing tips or ears.
            const head = champ.headTop();
            this.crown.position.set(head.x, head.y + 0.08 * s + drop * 2.2 + Math.sin(wall * 2) * 0.04, head.z);
            this.crown.rotation.y = wall * 0.8;
        }

        this.flashPower = (this.flashPower || 0) * Math.pow(0.02, dt);
        this.set.fxLight.intensity = this.flashPower;

        // Directed shots during the fight, then glide into the resting framing.
        const T = this.state === 'playing' ? this.t : END, F = this.final;
        let [camPos, look, fov] = cameraAt(Math.min(T, 6.4));
        camPos = look.clone().add(camPos.sub(look).multiplyScalar(this.zoom));
        const k = ease(span(T, 6.4, 7.7));
        camPos.lerp(F.pos, k);
        look.lerp(F.look, k);
        fov += (F.fov - fov) * k;
        if (Math.abs(this.camera.fov - fov) > 0.01) { this.camera.fov = fov; this.camera.updateProjectionMatrix(); }
        this.camera.position.copy(camPos);
        if (this.shake > 0) {
            this.camera.position.add(V((Math.random() - 0.5) * this.shake, (Math.random() - 0.5) * this.shake, 0));
            this.shake = Math.max(0, this.shake - dt * 1.2);
        }
        this.camera.lookAt(look);
        this.punch = Math.max(0, (this.punch || 0) - dt * 1.6);
        if (this.post) this.post.render(wall, { bloom: 0.32 + this.punch * 0.8 + (this.flashPower || 0) / 180, fringe: this.punch * 1.4 });
        else this.renderer.render(this.scene, this.camera);
        this.needsDraw = false;
        // The canvas stays hidden until its first full-size frame is on it.
        if (!this.shown) { this.shown = true; this.renderer.domElement.classList.add('is-drawn'); }

        // Tell the page where each podium landed so its details sit right under it.
        if (this.onLabels && (this.state === 'settled' || T >= 6.4)) {
            const w = this.host.clientWidth, at = {};
            for (const rank of [1, 2, 3]) {
                if (!this.actors[rank]) continue;
                const p = podiumSpot(rank).project(this.camera);
                at[rank] = { x: (p.x * 0.5 + 0.5) * w };
            }
            this.onLabels(at, this.picks);
        }
    }

    /**
     * The resting camera, solved for this stage's shape: close enough that the
     * podiums sit at about 21% / 50% / 79% of the width (over their details),
     * never so close that a tall champion's crown leaves the frame.
     */
    finalShot(aspect) {
        const fov = 40, tanH = Math.tan((fov / 2) * Math.PI / 180);
        const look = V(0, 1.42, 0.3);
        const across = PODIUM[3].x / (0.58 * tanH * aspect);
        // Keep the crown inside the solid middle of the faded stage.
        const tall = 2.25 / tanH;
        const dz = Math.max(across, tall);
        const tilt = 0.2;
        return { look, fov, pos: look.clone().add(V(0, Math.sin(tilt), Math.cos(tilt)).multiplyScalar(dz)) };
    }

    /** Height of the podium a fighter is standing on, once it has risen. */
    podiumTop(a) {
        const rank = +Object.keys(this.actors).find((r) => this.actors[r] === a);
        const p = this.set.podiums[rank];
        if (!p) return 0;
        const top = p.position.y + p.userData.h;
        const onIt = Math.abs(a.root.position.x - p.position.x) < 0.8 && Math.abs(a.root.position.z - p.position.z) < 0.8;
        return onIt && top > 0 ? top : 0;
    }

    setMuted(m) { this.sound.setMuted(m); }

    destroy() {
        this.running = false;
        this.ro.disconnect();
        this.io.disconnect();
        document.removeEventListener('visibilitychange', this.onVis);
        this.fx.clear();
        Object.values(this.actors).forEach((a) => a.dispose());
        this.scene.traverse((o) => { o.geometry?.dispose(); (Array.isArray(o.material) ? o.material : [o.material]).forEach((m) => { m?.map?.dispose(); m?.dispose(); }); });
        this.renderer.domElement.removeEventListener('webglcontextlost', this.onLost);
        this.renderer.domElement.removeEventListener('webglcontextrestored', this.onRestored);
        this.post?.dispose();
        this.scene.environment?.dispose();
        this.renderer.dispose();
        this.renderer.domElement.remove();
        this.sound.close();
    }
}

export { ROSTER };
