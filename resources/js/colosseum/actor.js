// One fighter on the arena floor. Wraps a cloned model in:
//   root  — where the timeline moves, turns and tips it
//   fit   — scales the model to its roster height and stands it on y = 0
// and plays the model's real clips when it has them. Bodies without a clip
// for a moment get motion layered onto their own bones instead (breathing,
// tail, arms, wings, head), which is what makes Pikachu and Charizard move.
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { MeshoptDecoder } from 'three/examples/jsm/libs/meshopt_decoder.module.js';
import { clone as cloneSkinned } from 'three/examples/jsm/utils/SkeletonUtils.js';
import { ROSTER } from './roster.js';

const loader = new GLTFLoader().setMeshoptDecoder(MeshoptDecoder);
const templates = new Map();

/** Load (once) and measure a roster model. */
export function loadTemplate(slug, base) {
    if (!templates.has(slug)) {
        templates.set(slug, loader.loadAsync(base + ROSTER[slug].file).then((gltf) => {
            const box = new THREE.Box3().setFromObject(gltf.scene, true);
            return { gltf, box };
        }));
    }
    return templates.get(slug);
}

const norm = (name) => name.toLowerCase().replace(/_\d+$/, '');

function findBones(model, cfg) {
    const all = [];
    model.traverse((o) => { if (o.isBone) all.push(o); });
    const pick = (re) => all.find((b) => re.test(norm(b.name)));
    const chain = (re) => all.filter((b) => re.test(norm(b.name)));
    return {
        spine: pick(/^spine(1|_01)?$/) || pick(/^spine/),
        head: pick(/^head$/),
        tail: chain(/^tail\d*$/),
        armL: pick(/^(larm|left_arm_01|left_arm)$/),
        armR: pick(/^(rarm|right_arm_01|right_arm)$/),
        earL: pick(/^(lear1?|left_ear)$/),
        earR: pick(/^(rear1?|right_ear)$/),
        wingL: cfg.wings ? chain(/^l.*/).filter((b) => cfg.wings.test(b.name)) : [],
        wingR: cfg.wings ? chain(/^r.*/).filter((b) => cfg.wings.test(b.name)) : [],
    };
}

export class Actor {
    constructor(slug, template, { lite }) {
        this.slug = slug;
        this.cfg = ROSTER[slug];
        this.root = new THREE.Group();
        this.fit = new THREE.Group();
        this.model = cloneSkinned(template.gltf.scene);
        const { box } = template;
        const size = box.getSize(new THREE.Vector3()), center = box.getCenter(new THREE.Vector3());
        const s = this.cfg.height / Math.max(0.001, size.y);
        this.fit.scale.setScalar(s);
        this.fit.position.set(-center.x * s, -box.min.y * s, -center.z * s);
        this.fit.add(this.model);
        this.root.add(this.fit);
        this.model.traverse((o) => {
            if (o.isMesh) {
                o.castShadow = !lite;
                o.frustumCulled = false;
            }
        });

        this.mixer = new THREE.AnimationMixer(this.model);
        this.clips = new Map(template.gltf.animations.map((c) => [c.name, c]));
        this.current = null;
        this.bones = findBones(this.model, this.cfg);
        this.rest = new Map();
        for (const b of Object.values(this.bones).flat()) if (b) this.rest.set(b, b.quaternion.clone());
        this.pose = { breathe: 1, tail: 1, wings: 0, wingsOpen: 0, armsUp: 0, headPitch: 0, lean: 0 };
        this.seed = Math.random() * 10;
    }

    get top() { return this.root.position.y + this.cfg.height * this.root.scale.y; }

    /** World position of the top of the head: the head bone lifted by a head's height. */
    headTop() {
        const head = this.bones.head;
        if (!head) return new THREE.Vector3(this.root.position.x, this.top, this.root.position.z);
        const p = head.getWorldPosition(new THREE.Vector3());
        p.y += this.cfg.height * (this.cfg.crownLift ?? 0.14);
        return p;
    }

    has(name) { return this.clips.has(name); }

    /** Play a clip; returns false when the model doesn't have it. */
    play(name, { loop = false, timeScale = 1, fade = 0.2, at = 0 } = {}) {
        const clip = this.clips.get(name);
        if (!clip) return false;
        const action = this.mixer.clipAction(clip);
        action.reset();
        action.setLoop(loop ? THREE.LoopRepeat : THREE.LoopOnce, loop ? Infinity : 1);
        action.clampWhenFinished = !loop;
        action.timeScale = timeScale;
        action.time = at;
        action.enabled = true;
        if (this.current && this.current !== action) this.current.crossFadeTo(action, fade, false);
        action.play();
        this.current = action;
        return true;
    }

    /** Back to the resting loop: the idle clip, or no clip and bone motion. */
    rest_() {
        if (this.play('battle', { loop: true, fade: 0.35 }) || this.play('idle', { loop: true, fade: 0.35 })) return;
        if (this.current) { this.current.fadeOut(0.25); this.current = null; }
    }

    clipActive() {
        return !!this.current && this.current.isRunning() && this.current.getEffectiveWeight() > 0.05;
    }

    update(dt, t) {
        this.mixer.update(dt);
        if (this.clipActive()) return;
        // No clip driving the skeleton: layer motion onto the rest pose.
        const P = this.pose, B = this.bones, e = new THREE.Euler(), q = new THREE.Quaternion();
        const set = (bone, x, y, z) => {
            if (!bone) return;
            bone.quaternion.copy(this.rest.get(bone)).multiply(q.setFromEuler(e.set(x, y, z)));
        };
        const w = t + this.seed;
        set(B.spine, P.lean * 0.6 + Math.sin(w * 2.4) * 0.035 * P.breathe, 0, 0);
        set(B.head, P.headPitch + Math.sin(w * 2.4 + 1) * 0.03, Math.sin(w * 0.7) * 0.08, 0);
        B.tail.forEach((b, i) => set(b, 0, Math.sin(w * 3.1 - i * 0.7) * 0.22 * P.tail, Math.sin(w * 1.7 - i) * 0.05));
        set(B.armL, 0, 0, P.armsUp * 0.9 + Math.sin(w * 2.4) * 0.03);
        set(B.armR, 0, 0, -P.armsUp * 0.9 - Math.sin(w * 2.4) * 0.03);
        const twitch = Math.max(0, Math.sin(w * 0.9)) ** 12 * 0.25;
        set(B.earL, twitch, 0, 0);
        set(B.earR, twitch, 0, 0);
        const flap = Math.sin(w * 9) * 0.45 * P.wings + P.wingsOpen * 0.5 + Math.sin(w * 1.6) * 0.05;
        B.wingL.forEach((b, i) => set(b, 0, 0, flap * (i ? 0.35 : 1)));
        B.wingR.forEach((b, i) => set(b, 0, 0, -flap * (i ? 0.35 : 1)));
    }

    dispose() {
        this.mixer.stopAllAction();
        this.root.removeFromParent();
    }
}
