// Battle effects, all built from a shared soft-glow sprite so they stay cheap:
// particle bursts (dust, sparks, gold), expanding impact rings, a jagged
// lightning bolt, a fire stream, travelling energy orbs and the crown.
import * as THREE from 'three';

export class Effects {
    constructor(scene, glow, { lite }) {
        this.scene = scene;
        this.glow = glow;
        this.lite = lite;
        this.items = [];
    }

    clear() {
        for (const it of this.items) it.dispose();
        this.items = [];
    }

    update(dt) {
        // Effects spawned during a tick (an orb's trail sparks) land in the new
        // list; filtering in place used to drop them, leaving them frozen mid-air.
        const current = this.items;
        this.items = [];
        for (const it of current) {
            it.age += dt;
            if (it.age >= it.life) { it.dispose(); continue; }
            it.tick(it.age / it.life, dt);
            this.items.push(it);
        }
    }

    add(obj, life, tick) {
        this.scene.add(obj);
        const it = {
            age: 0, life, tick,
            // Disposing a material leaves its texture alone, so the shared glow survives.
            dispose: () => { this.scene.remove(obj); obj.traverse?.((o) => { o.geometry?.dispose(); o.material?.dispose?.(); }); },
        };
        this.items.push(it);
        return it;
    }

    /** Particles thrown from a point: dust (grey, low), sparks, gold confetti. */
    burst(at, { color = 0xffffff, count = 24, speed = 2, up = 1, gravity = -3, size = 0.14, life = 0.9, spread = 1, opacity = 0.9, additive = true } = {}) {
        count = Math.round(this.lite ? count * 0.55 : count);
        const g = new THREE.BufferGeometry();
        const pos = new Float32Array(count * 3), vel = [];
        for (let i = 0; i < count; i++) {
            pos.set([at.x, at.y, at.z], i * 3);
            const a = Math.random() * Math.PI * 2, s = speed * (0.4 + Math.random() * 0.8);
            vel.push(new THREE.Vector3(Math.cos(a) * s * spread, (0.3 + Math.random()) * up * speed, Math.sin(a) * s * spread));
        }
        g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        const mat = new THREE.PointsMaterial({ map: this.glow, color, size, transparent: true, opacity, depthWrite: false, blending: additive ? THREE.AdditiveBlending : THREE.NormalBlending });
        const pts = new THREE.Points(g, mat);
        this.add(pts, life, (k, dt) => {
            const p = g.attributes.position;
            for (let i = 0; i < count; i++) {
                vel[i].y += gravity * dt;
                p.setXYZ(i, p.getX(i) + vel[i].x * dt, Math.max(0.02, p.getY(i) + vel[i].y * dt), p.getZ(i) + vel[i].z * dt);
            }
            p.needsUpdate = true;
            mat.opacity = opacity * (1 - k);
        });
    }

    dust(at, scale = 1) {
        this.burst(new THREE.Vector3(at.x, 0.08, at.z), { color: 0xbdb3cf, count: 18 * scale, speed: 1.3 * scale, up: 0.35, gravity: -0.6, size: 0.42 * scale, life: 1.1, opacity: 0.4, additive: false });
    }

    /** Flat ring that expands along the floor (or facing up at a point). */
    ring(at, color, { radius = 1.6, life = 0.55, y = 0.06 } = {}) {
        const mat = new THREE.MeshBasicMaterial({ color, transparent: true, opacity: 0.9, depthWrite: false, blending: THREE.AdditiveBlending, side: THREE.DoubleSide });
        const m = new THREE.Mesh(new THREE.RingGeometry(0.7, 1, 40), mat);
        m.rotation.x = -Math.PI / 2;
        m.position.set(at.x, y, at.z);
        this.add(m, life, (k) => { m.scale.setScalar(0.2 + k * radius); mat.opacity = 0.9 * (1 - k); });
    }

    /** A glow sprite that swells (charge-up) or flashes (impact). */
    glowAt(at, color, { from = 0.2, to = 1.4, life = 0.4, opacity = 1 } = {}) {
        const mat = new THREE.SpriteMaterial({ map: this.glow, color, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending });
        const s = new THREE.Sprite(mat);
        s.position.copy(at);
        this.add(s, life, (k) => { s.scale.setScalar(from + (to - from) * k); mat.opacity = opacity * Math.sin(Math.PI * Math.min(1, k * 1.15)); });
        return s;
    }

    /** Jagged electric arc between two points, re-drawn every frame, with branches. */
    bolt(from, to, color, life = 0.6) {
        const segs = 14, group = new THREE.Group();
        const lines = [0, 1, 2].map((i) => {
            const g = new THREE.BufferGeometry();
            g.setAttribute('position', new THREE.BufferAttribute(new Float32Array((segs + 1) * 3), 3));
            const l = new THREE.Line(g, new THREE.LineBasicMaterial({ color: i ? 0xffffff : color, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false }));
            group.add(l);
            return l;
        });
        const dir = new THREE.Vector3(), side = new THREE.Vector3();
        this.add(group, life, (k) => {
            lines.forEach((line, li) => {
                const p = line.geometry.attributes.position;
                const end = li === 2 ? from.clone().lerp(to, 0.55).add(new THREE.Vector3(0, 0.6, 0)) : to;
                dir.subVectors(end, from);
                side.set(-dir.z, 0, dir.x).normalize();
                for (let i = 0; i <= segs; i++) {
                    const f = i / segs, jitter = i === 0 || i === segs ? 0 : (Math.random() - 0.5) * 0.45;
                    p.setXYZ(i, from.x + dir.x * f + side.x * jitter, from.y + dir.y * f + (Math.random() - 0.5) * 0.3 * (i % segs ? 1 : 0), from.z + dir.z * f + side.z * jitter);
                }
                p.needsUpdate = true;
                line.material.opacity = (k < 0.85 ? 1 : (1 - k) / 0.15) * (li ? 0.8 : 1);
            });
        });
    }

    /** Fire breath: particles streaming from the mouth, sweeping between two targets. */
    stream(from, targets, color, life = 0.75) {
        const count = this.lite ? 70 : 140;
        const g = new THREE.BufferGeometry();
        const pos = new Float32Array(count * 3), born = new Float32Array(count), dirs = [];
        for (let i = 0; i < count; i++) { born[i] = (i / count) * life * 0.8; dirs.push(new THREE.Vector3()); pos.set([from.x, from.y, from.z], i * 3); }
        g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        const mat = new THREE.PointsMaterial({ map: this.glow, color, size: 0.42, transparent: true, opacity: 0.75, depthWrite: false, blending: THREE.AdditiveBlending });
        const pts = new THREE.Points(g, mat);
        let elapsed = 0;
        this.add(pts, life + 0.35, (k, dt) => {
            elapsed += dt;
            const p = g.attributes.position;
            for (let i = 0; i < count; i++) {
                const age = elapsed - born[i];
                if (age < 0) continue;
                if (dirs[i].lengthSq() === 0) {
                    const sweep = Math.min(1, born[i] / (life * 0.8));
                    const target = targets[0].clone().lerp(targets[targets.length - 1], sweep);
                    dirs[i].subVectors(target, from).normalize().multiplyScalar(5.5).add(new THREE.Vector3((Math.random() - 0.5) * 0.8, (Math.random() - 0.2) * 0.6, (Math.random() - 0.5) * 0.8));
                }
                p.setXYZ(i, from.x + dirs[i].x * age, from.y + dirs[i].y * age, from.z + dirs[i].z * age);
            }
            p.needsUpdate = true;
            mat.opacity = 0.75 * (k < 0.8 ? 1 : (1 - k) / 0.2);
            mat.color.setHSL(0.06 - k * 0.04, 1, 0.42);
        });
    }

    /** An energy orb flying from a to b with a trail; calls onHit on arrival. */
    orb(from, to, color, travel = 0.5, onHit) {
        const core = new THREE.Sprite(new THREE.SpriteMaterial({ map: this.glow, color, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending }));
        core.scale.setScalar(0.6);
        const inner = new THREE.Sprite(new THREE.SpriteMaterial({ map: this.glow, color: 0xffffff, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending }));
        inner.scale.setScalar(0.28);
        const group = new THREE.Group();
        group.add(core, inner);
        let hit = false;
        const mid = from.clone().lerp(to, 0.5).add(new THREE.Vector3(0, 0.5, 0));
        this.add(group, travel, (k) => {
            const a = from.clone().lerp(mid, k), b = mid.clone().lerp(to, k);
            group.position.copy(a.lerp(b, k));
            core.scale.setScalar(0.55 + Math.sin(k * 30) * 0.06);
            if (Math.random() < 0.7) this.burst(group.position, { color, count: 2, speed: 0.3, up: 0.2, gravity: 0, size: 0.18, life: 0.3 });
            if (k > 0.96 && !hit) { hit = true; onHit?.(); }
        });
        setTimeout(() => { if (!hit) { hit = true; onHit?.(); } }, travel * 1000 + 50);
    }
}

/** A small gold crown built from primitives. */
export function buildCrown() {
    const gold = new THREE.MeshStandardMaterial({ color: 0xf5c542, metalness: 0.9, roughness: 0.28, emissive: 0x6b4a00, emissiveIntensity: 0.6 });
    const gem = new THREE.MeshStandardMaterial({ color: 0x8b5cf6, emissive: 0x7c3aed, emissiveIntensity: 0.9, roughness: 0.2 });
    const crown = new THREE.Group();
    const band = new THREE.Mesh(new THREE.CylinderGeometry(0.26, 0.24, 0.13, 24, 1, true), gold);
    band.material.side = THREE.DoubleSide;
    crown.add(band);
    for (let i = 0; i < 5; i++) {
        const a = (i / 5) * Math.PI * 2;
        const spike = new THREE.Mesh(new THREE.ConeGeometry(0.06, 0.17, 6), gold);
        spike.position.set(Math.cos(a) * 0.24, 0.14, Math.sin(a) * 0.24);
        crown.add(spike);
        const ball = new THREE.Mesh(new THREE.SphereGeometry(0.028, 8, 8), gold);
        ball.position.set(Math.cos(a) * 0.24, 0.235, Math.sin(a) * 0.24);
        crown.add(ball);
    }
    const front = new THREE.Mesh(new THREE.OctahedronGeometry(0.05), gem);
    front.position.set(0, 0.01, 0.255);
    crown.add(front);
    crown.visible = false;
    return crown;
}
