// The Vortex Colosseum set: stone floor with the Vortex emblem, low wall with
// purple trim, three entrance gates, Vortex banners, stepped stands with a
// silhouette crowd, three podiums, lights and haze. Every texture is drawn on a
// canvas here, so the set downloads nothing.
import * as THREE from 'three';

const PURPLE = '#7c3aed';
const PURPLE_DEEP = '#4c1d95';

/** The Vortex "V" mark, scaled into a box at (x, y) of size s. */
function drawV(ctx, x, y, s, color = '#fff') {
    const p = [[50, 87], [135, 87], [257, 315], [378, 87], [460, 87], [257, 425]];
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(s / 512, s / 512);
    ctx.fillStyle = color;
    ctx.beginPath();
    p.forEach(([px, py], i) => (i ? ctx.lineTo(px, py) : ctx.moveTo(px, py)));
    ctx.closePath();
    ctx.fill();
    ctx.restore();
}

function canvasTexture(w, h, draw, repeat) {
    const c = document.createElement('canvas');
    c.width = w;
    c.height = h;
    draw(c.getContext('2d'), w, h);
    const t = new THREE.CanvasTexture(c);
    t.userData.canvas = c;
    t.colorSpace = THREE.SRGBColorSpace;
    t.anisotropy = 4;
    if (repeat) {
        t.wrapS = t.wrapT = THREE.RepeatWrapping;
        t.repeat.set(repeat[0], repeat[1]);
    }
    return t;
}

/** A normal map from a texture's brightness: dark grout reads as recessed. */
function normalFrom(tex, strength = 2.2) {
    // Bump detail reads the same at 512 px and costs a quarter of the work.
    let src = tex.userData.canvas;
    if (src.width > 512) {
        const small = document.createElement('canvas');
        small.width = small.height = 512;
        small.getContext('2d').drawImage(src, 0, 0, 512, 512);
        src = small;
    }
    const w = src.width, h = src.height;
    const px = src.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, w, h).data;
    const out = document.createElement('canvas');
    out.width = w;
    out.height = h;
    const ctx = out.getContext('2d'), img = ctx.createImageData(w, h);
    const lum = (x, y) => { const i = (((y + h) % h) * w + ((x + w) % w)) * 4; return (px[i] + px[i + 1] + px[i + 2]) / 765; };
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const dx = (lum(x + 1, y) - lum(x - 1, y)) * strength, dy = (lum(x, y + 1) - lum(x, y - 1)) * strength;
            const len = Math.hypot(dx, dy, 1), i = (y * w + x) * 4;
            img.data[i] = (-dx / len * 0.5 + 0.5) * 255;
            img.data[i + 1] = (-dy / len * 0.5 + 0.5) * 255;
            img.data[i + 2] = (1 / len * 0.5 + 0.5) * 255;
            img.data[i + 3] = 255;
        }
    }
    ctx.putImageData(img, 0, 0);
    const t = new THREE.CanvasTexture(out);
    t.wrapS = tex.wrapS;
    t.wrapT = tex.wrapT;
    t.repeat.copy(tex.repeat);
    return t;
}

/** Polished centre, rougher outer stone. */
function floorRoughness() {
    return canvasTexture(256, 256, (ctx, w, h) => {
        const g = ctx.createRadialGradient(w / 2, h / 2, 10, w / 2, h / 2, w / 2);
        g.addColorStop(0, '#3a3a3a');
        g.addColorStop(0.34, '#5a5a5a');
        g.addColorStop(0.42, '#e6e6e6');
        g.addColorStop(1, '#f2f2f2');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, w, h);
    });
}

function noise(ctx, w, h, n, alpha) {
    for (let i = 0; i < n; i++) {
        const v = Math.random() * 255 | 0;
        ctx.fillStyle = `rgba(${v},${v},${v + 10},${alpha})`;
        ctx.fillRect(Math.random() * w, Math.random() * h, 2, 2);
    }
}

function floorTexture() {
    return canvasTexture(1024, 1024, (ctx, w, h) => {
        const cx = w / 2, cy = h / 2;
        ctx.fillStyle = '#8d8798';
        ctx.fillRect(0, 0, w, h);
        // Concentric rings of flagstones.
        for (let ring = 0; ring < 9; ring++) {
            const r0 = 60 + ring * 52, r1 = r0 + 50, n = 10 + ring * 6;
            for (let k = 0; k < n; k++) {
                const a0 = (k / n) * Math.PI * 2 + ring * 0.21, a1 = ((k + 1) / n) * Math.PI * 2 + ring * 0.21;
                const g = 128 + (Math.random() * 26 - 13) | 0;
                ctx.fillStyle = `rgb(${g},${g - 6},${g + 10})`;
                ctx.beginPath();
                ctx.arc(cx, cy, r1, a0, a1);
                ctx.arc(cx, cy, r0, a1, a0, true);
                ctx.closePath();
                ctx.fill();
                ctx.strokeStyle = 'rgba(40,30,60,.55)';
                ctx.lineWidth = 3;
                ctx.stroke();
            }
        }
        noise(ctx, w, h, 9000, 0.08);
        // Purple battle ring and centre emblem.
        ctx.strokeStyle = 'rgba(124,58,237,.85)';
        ctx.lineWidth = 10;
        ctx.beginPath();
        ctx.arc(cx, cy, 300, 0, Math.PI * 2);
        ctx.stroke();
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.arc(cx, cy, 322, 0, Math.PI * 2);
        ctx.stroke();
        const grd = ctx.createRadialGradient(cx, cy, 10, cx, cy, 150);
        grd.addColorStop(0, 'rgba(124,58,237,.95)');
        grd.addColorStop(1, 'rgba(76,29,149,.85)');
        ctx.fillStyle = grd;
        ctx.beginPath();
        ctx.arc(cx, cy, 130, 0, Math.PI * 2);
        ctx.fill();
        drawV(ctx, cx - 95, cy - 100, 190, 'rgba(255,255,255,.95)');
        ctx.font = '700 34px Inter, system-ui, sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,.85)';
        ctx.textAlign = 'center';
        ctx.fillText('VORTEX OPS', cx, cy + 190);
        ctx.font = '600 22px Inter, system-ui, sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,.6)';
        ctx.fillText('C O L O S S E U M', cx, cy + 222);
    });
}

function stoneTexture(repeat) {
    return canvasTexture(256, 128, (ctx, w, h) => {
        ctx.fillStyle = '#6f6a7c';
        ctx.fillRect(0, 0, w, h);
        for (let row = 0; row < 4; row++) {
            for (let col = 0; col < 5; col++) {
                const g = 100 + Math.random() * 24 | 0;
                ctx.fillStyle = `rgb(${g},${g - 4},${g + 12})`;
                ctx.fillRect(col * 52 + (row % 2) * 26 - 26, row * 32 + 2, 50, 29);
            }
        }
        noise(ctx, w, h, 1800, 0.1);
    }, repeat);
}

function bannerTexture() {
    return canvasTexture(256, 512, (ctx, w, h) => {
        const g = ctx.createLinearGradient(0, 0, 0, h);
        g.addColorStop(0, PURPLE);
        g.addColorStop(1, PURPLE_DEEP);
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, w, h);
        ctx.fillStyle = 'rgba(255,214,102,.9)';
        ctx.fillRect(0, 0, w, 14);
        ctx.fillRect(0, h - 70, w, 8);
        drawV(ctx, 38, 110, 180);
        ctx.font = '800 38px Inter, system-ui, sans-serif';
        ctx.fillStyle = '#fff';
        ctx.textAlign = 'center';
        ctx.fillText('VORTEX', w / 2, 360);
        ctx.font = '700 30px Inter, system-ui, sans-serif';
        ctx.fillStyle = 'rgba(255,255,255,.8)';
        ctx.fillText('OPS', w / 2, 400);
        // Swallow-tail cut at the bottom.
        ctx.globalCompositeOperation = 'destination-out';
        ctx.beginPath();
        ctx.moveTo(0, h);
        ctx.lineTo(w / 2, h - 60);
        ctx.lineTo(w, h);
        ctx.closePath();
        ctx.fill();
    });
}

function glowTexture() {
    return canvasTexture(64, 64, (ctx) => {
        const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
        g.addColorStop(0, 'rgba(255,255,255,1)');
        g.addColorStop(0.35, 'rgba(255,255,255,.55)');
        g.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, 64, 64);
    });
}

export const PODIUM = {
    // rank => x position and podium height
    1: { x: 0, h: 0.95, color: 0xf5b82e },
    2: { x: -2.35, h: 0.62, color: 0xc6cbe0 },
    3: { x: 2.35, h: 0.42, color: 0xd98f6c },
};

export const GATES = {
    // where each fighter walks in from (angle around the arena, radians)
    1: -Math.PI / 2,
    2: -Math.PI / 2 - 0.95,
    3: -Math.PI / 2 + 0.95,
};

// The stands leave this half-angle open on the camera side, around +z.
const OPEN_FRONT = Math.PI * 0.3;

export function buildStage(scene, { lite }) {
    const set = { glow: glowTexture(), gateLights: [], spots: [], podiums: {}, crowd: null, dust: null, banners: [] };
    const R = 6.4;

    scene.background = new THREE.Color(0x120d26);
    scene.fog = new THREE.Fog(0x1a1236, 13, 30);

    // Floor.
    const floorMap = floorTexture();
    const floorRough = floorRoughness();
    floorRough.colorSpace = THREE.NoColorSpace;
    const floor = new THREE.Mesh(
        new THREE.CircleGeometry(R, 72),
        new THREE.MeshStandardMaterial({ map: floorMap, normalMap: lite ? null : normalFrom(floorMap, 3), normalScale: new THREE.Vector2(0.9, 0.9), roughnessMap: floorRough, roughness: 1, metalness: 0.05, envMapIntensity: 0.9 }),
    );
    floor.rotation.x = -Math.PI / 2;
    floor.receiveShadow = true;
    scene.add(floor);

    // Low wall with a glowing purple trim.
    const wallTex = stoneTexture([16, 1]);
    const wallMat = new THREE.MeshStandardMaterial({ map: wallTex, normalMap: lite ? null : normalFrom(wallTex, 4), roughness: 0.95, side: THREE.DoubleSide });
    const wall = new THREE.Mesh(new THREE.CylinderGeometry(R + 0.15, R + 0.15, 1.1, 72, 1, true), wallMat);
    wall.position.y = 0.55;
    scene.add(wall);
    const trim = new THREE.Mesh(
        new THREE.TorusGeometry(R + 0.15, 0.05, 6, 96),
        new THREE.MeshStandardMaterial({ color: 0x8b5cf6, emissive: 0x7c3aed, emissiveIntensity: 1.6 }),
    );
    trim.rotation.x = Math.PI / 2;
    trim.position.y = 1.12;
    scene.add(trim);
    set.trim = trim;

    // Stands: stepped rings rising behind the wall, with a silhouette crowd.
    const standMat = new THREE.MeshStandardMaterial({ map: stoneTexture([28, 1]), color: 0x8a84a0, roughness: 1, side: THREE.DoubleSide });
    for (let i = 0; i < 4; i++) {
        const r = R + 0.9 + i * 1.05, y = 1.1 + i * 0.75;
        const step = new THREE.Mesh(new THREE.CylinderGeometry(r + 0.55, r + 0.55, 0.75, 60, 1, true, OPEN_FRONT, Math.PI * 2 - OPEN_FRONT * 2), standMat);
        step.position.y = y;
        scene.add(step);
        const top = new THREE.Mesh(new THREE.RingGeometry(r - 0.5, r + 0.55, 60, 1, -Math.PI / 2 + OPEN_FRONT, Math.PI * 2 - OPEN_FRONT * 2), standMat);
        top.rotation.x = -Math.PI / 2;
        top.position.y = y + 0.375;
        scene.add(top);
    }
    const crowdCount = lite ? 160 : 340;
    const crowd = new THREE.InstancedMesh(
        new THREE.CapsuleGeometry(0.17, 0.32, 3, 6),
        new THREE.MeshStandardMaterial({ color: 0x2a2350, roughness: 1 }),
        crowdCount,
    );
    const m = new THREE.Matrix4();
    crowd.userData.base = [];
    for (let i = 0; i < crowdCount; i++) {
        const row = i % 4, a = Math.PI / 2 + OPEN_FRONT + Math.random() * (Math.PI * 2 - OPEN_FRONT * 2); // all but the camera side
        const r = R + 0.75 + row * 1.05 + Math.random() * 0.3, y = 1.95 + row * 0.75;
        crowd.userData.base.push([Math.cos(a) * r, y, Math.sin(a) * r, Math.random() * 6]);
        m.makeTranslation(Math.cos(a) * r, y, Math.sin(a) * r);
        crowd.setMatrixAt(i, m);
        crowd.setColorAt(i, new THREE.Color().setHSL(0.72 + Math.random() * 0.08, 0.35, 0.16 + Math.random() * 0.1));
    }
    scene.add(crowd);
    set.crowd = crowd;

    // Three gates with lights inside, and banners between them.
    const gateTex = stoneTexture([1, 2]);
    const gateMat = new THREE.MeshStandardMaterial({ map: gateTex, normalMap: lite ? null : normalFrom(gateTex, 4), color: 0xb8b0c8, roughness: 0.9 });
    const darkMat = new THREE.MeshBasicMaterial({ color: 0x07050f });
    for (const rank of [1, 2, 3]) {
        const a = GATES[rank];
        const g = new THREE.Group();
        g.position.set(Math.cos(a) * (R + 0.2), 0, Math.sin(a) * (R + 0.2));
        g.lookAt(0, 0, 0);
        for (const sx of [-0.95, 0.95]) {
            const pillar = new THREE.Mesh(new THREE.BoxGeometry(0.42, 2.3, 0.6), gateMat);
            pillar.position.set(sx, 1.15, 0);
            pillar.castShadow = !lite;
            g.add(pillar);
        }
        const lintel = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.42, 0.66), gateMat);
        lintel.position.y = 2.4;
        g.add(lintel);
        const emblem = new THREE.Mesh(new THREE.PlaneGeometry(0.5, 0.5), new THREE.MeshBasicMaterial({ map: canvasTexture(128, 128, (ctx) => {
            ctx.fillStyle = PURPLE;
            ctx.beginPath();
            ctx.arc(64, 64, 60, 0, Math.PI * 2);
            ctx.fill();
            drawV(ctx, 22, 20, 84);
        }), transparent: true }));
        emblem.position.set(0, 2.4, 0.34);
        g.add(emblem);
        const hole = new THREE.Mesh(new THREE.PlaneGeometry(1.5, 2.2), darkMat);
        hole.position.set(0, 1.1, -0.05);
        g.add(hole);
        const light = new THREE.PointLight(0xb794ff, 0, 5, 1.6);
        light.position.set(0, 1.4, 0.7);
        g.add(light);
        set.gateLights.push(light);
        scene.add(g);
    }
    const bannerTex = bannerTexture();
    const bannerAngles = [-Math.PI / 2 - 0.48, -Math.PI / 2 + 0.48, -Math.PI / 2 - 1.45, -Math.PI / 2 + 1.45];
    for (const a of bannerAngles) {
        const b = new THREE.Mesh(
            new THREE.PlaneGeometry(0.85, 1.7, 1, 6),
            new THREE.MeshStandardMaterial({ map: bannerTex, transparent: true, side: THREE.DoubleSide, roughness: 0.8, emissive: 0x2a0f5c, emissiveIntensity: 0.25 }),
        );
        b.position.set(Math.cos(a) * (R + 0.02), 1.95, Math.sin(a) * (R + 0.02));
        b.lookAt(0, 1.95, 0);
        b.userData.phase = a * 3;
        scene.add(b);
        set.banners.push(b);
    }

    // Podiums start below the floor and rise into place for the finale.
    for (const rank of [1, 2, 3]) {
        const { x, h, color } = PODIUM[rank];
        const g = new THREE.Group();
        const body = new THREE.Mesh(
            new THREE.CylinderGeometry(0.82, 0.92, h, 40),
            new THREE.MeshStandardMaterial({ map: stoneTexture([4, 1]), color: 0xd8d2e6, roughness: 0.85 }),
        );
        body.position.y = h / 2;
        body.castShadow = body.receiveShadow = !lite;
        g.add(body);
        const band = new THREE.Mesh(
            new THREE.TorusGeometry(0.84, 0.045, 6, 48),
            new THREE.MeshStandardMaterial({ color, emissive: color, emissiveIntensity: 0.55, metalness: 0.8, roughness: 0.3 }),
        );
        band.rotation.x = Math.PI / 2;
        band.position.y = h - 0.06;
        g.add(band);
        g.position.set(x, -h - 0.05, 0.3);
        g.userData = { h, x };
        scene.add(g);
        set.podiums[rank] = g;
    }

    // Lights: lavender fill, a key light with soft shadows, three spotlights
    // on the floor and a warm champion light that comes up at the end.
    scene.add(new THREE.HemisphereLight(0xc9b8ff, 0x231a3d, 0.42));
    const key = new THREE.DirectionalLight(0xffffff, 1.4);
    key.position.set(3, 9, 6);
    if (!lite) {
        key.castShadow = true;
        key.shadow.mapSize.set(1024, 1024);
        Object.assign(key.shadow.camera, { left: -6, right: 6, top: 6, bottom: -6, near: 1, far: 22 });
        key.shadow.radius = 4;
        key.shadow.bias = -0.0008;
    }
    scene.add(key);
    set.key = key;
    for (const [x, z] of [[-4, 3], [4, 3], [0, -4.5]]) {
        const s = new THREE.SpotLight(0xb48cff, 0, 20, 0.55, 0.6, 1.2);
        s.position.set(x, 8.5, z);
        s.target.position.set(x * 0.15, 0, z * 0.1);
        scene.add(s, s.target);
        set.spots.push(s);
    }
    const champ = new THREE.SpotLight(0xffc766, 0, 14, 0.32, 0.7, 1.1);
    champ.position.set(0, 7.5, 3.5);
    champ.target.position.set(0, 1, 0.3);
    scene.add(champ, champ.target);
    set.champLight = champ;
    set.fxLight = new THREE.PointLight(0xffffff, 0, 9, 1.5);
    scene.add(set.fxLight);

    // A few floating motes in the haze.
    const motes = new THREE.BufferGeometry();
    const n = lite ? 40 : 90, pos = new Float32Array(n * 3);
    for (let i = 0; i < n; i++) {
        pos[i * 3] = (Math.random() - 0.5) * 12;
        pos[i * 3 + 1] = Math.random() * 5 + 0.3;
        pos[i * 3 + 2] = (Math.random() - 0.5) * 10;
    }
    motes.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    set.motes = new THREE.Points(motes, new THREE.PointsMaterial({ map: set.glow, size: 0.09, transparent: true, opacity: 0.45, color: 0xd9c8ff, depthWrite: false, blending: THREE.AdditiveBlending }));
    scene.add(set.motes);

    addDetail(scene, set, { lite, R, gateMat });
    return set;
}

/**
 * The cinematic layer: columns with arches behind the wall, flaming braziers
 * with flickering light and embers, soft light shafts under the spotlights and
 * camera flashes popping in the crowd.
 */
function addDetail(scene, set, { lite, R, gateMat }) {
    // Columns between the stands, on the camera's far side.
    const colGeo = new THREE.CylinderGeometry(0.26, 0.3, 3.8, 14);
    const capGeo = new THREE.BoxGeometry(0.75, 0.22, 0.75);
    const archMat = gateMat.clone();
    archMat.color = new THREE.Color(0xa59cb8);
    for (let i = 0; i < 12; i++) {
        const a = Math.PI / 2 + 0.75 + (i / 11) * (Math.PI * 2 - 1.5);
        const x = Math.cos(a) * (R + 4.6), z = Math.sin(a) * (R + 4.6);
        const col = new THREE.Mesh(colGeo, archMat);
        col.position.set(x, 3.6, z);
        const cap = new THREE.Mesh(capGeo, archMat);
        cap.position.set(x, 5.6, z);
        scene.add(col, cap);
    }
    const ring = new THREE.Mesh(new THREE.TorusGeometry(R + 4.6, 0.16, 6, 90, Math.PI * 2 - 1.5), archMat);
    ring.rotation.x = Math.PI / 2;
    ring.rotation.z = Math.PI / 2 + 0.75;
    ring.position.y = 5.75;
    scene.add(ring);

    // Braziers on the wall: bowl, layered flame sprites, a flickering light.
    set.braziers = [];
    const bowlMat = new THREE.MeshStandardMaterial({ color: 0x3b3248, metalness: 0.7, roughness: 0.4 });
    const angles = [-Math.PI / 2 - 0.48, -Math.PI / 2 + 0.48, -Math.PI / 2 - 1.45, -Math.PI / 2 + 1.45, 0.28, Math.PI - 0.28];
    angles.forEach((a, i) => {
        const g = new THREE.Group();
        g.position.set(Math.cos(a) * (R + 0.15), 1.12, Math.sin(a) * (R + 0.15));
        const bowl = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.12, 0.2, 14, 1, true), bowlMat);
        bowl.material.side = THREE.DoubleSide;
        bowl.position.y = 0.1;
        g.add(bowl);
        const flames = [0, 1, 2].map((k) => {
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: set.glow, color: k ? 0xff9a2e : 0xffe0a0, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending }));
            sp.position.y = 0.28 + k * 0.1;
            g.add(sp);
            return sp;
        });
        let light = null;
        if (!lite && i < 4) {
            light = new THREE.PointLight(0xff9a40, 4, 6, 1.8);
            light.position.y = 0.55;
            g.add(light);
        }
        scene.add(g);
        set.braziers.push({ g, flames, light, phase: i * 1.7 });
    });

    // Embers rising from the braziers.
    const n = lite ? 30 : 80, pos = new Float32Array(n * 3);
    set.embers = { n, seeds: [] };
    for (let i = 0; i < n; i++) {
        const b = set.braziers[i % set.braziers.length].g.position;
        set.embers.seeds.push([b.x, b.z, Math.random() * 3, 0.3 + Math.random() * 0.5]);
    }
    const eg = new THREE.BufferGeometry();
    eg.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    set.embers.points = new THREE.Points(eg, new THREE.PointsMaterial({ map: set.glow, color: 0xffa040, size: 0.07, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending }));
    scene.add(set.embers.points);

    // Light shafts: soft additive cones under each spotlight.
    const shaftMat = new THREE.ShaderMaterial({
        transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, side: THREE.DoubleSide,
        uniforms: { uColor: { value: new THREE.Color(0xb48cff) }, uPower: { value: 0 } },
        vertexShader: 'varying float vY; varying vec3 vN; varying vec3 vV; void main(){ vY = uv.y; vN = normalize(normalMatrix * normal); vec4 mv = modelViewMatrix * vec4(position,1.0); vV = normalize(-mv.xyz); gl_Position = projectionMatrix * mv; }',
        fragmentShader: 'uniform vec3 uColor; uniform float uPower; varying float vY; varying vec3 vN; varying vec3 vV; void main(){ float rim = pow(abs(dot(vN, vV)), 1.6); gl_FragColor = vec4(uColor, uPower * 0.11 * rim * smoothstep(0.0, 0.9, vY)); }',
    });
    set.shafts = [];
    for (const s of set.spots) {
        const h = s.position.y;
        const cone = new THREE.Mesh(new THREE.ConeGeometry(Math.tan(s.angle) * h, h, 32, 1, true), shaftMat.clone());
        const mid = s.position.clone().add(s.target.position).multiplyScalar(0.5);
        cone.position.copy(mid);
        cone.lookAt(s.target.position);
        cone.rotateX(-Math.PI / 2);
        scene.add(cone);
        set.shafts.push(cone);
    }

    // Camera flashes popping in the crowd.
    set.flashes = [];
    if (!lite) {
        for (let i = 0; i < 10; i++) {
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: set.glow, color: 0xffffff, transparent: true, opacity: 0, depthWrite: false, blending: THREE.AdditiveBlending }));
            sp.scale.setScalar(0.5);
            scene.add(sp);
            set.flashes.push({ sp, next: Math.random() * 3, life: 0 });
        }
    }

    // Soft contact shadows for fighters (always on, so phones get grounding too).
    set.blob = canvasTexture(64, 64, (ctx) => {
        const g = ctx.createRadialGradient(32, 32, 0, 32, 32, 32);
        g.addColorStop(0, 'rgba(10,4,25,.75)');
        g.addColorStop(1, 'rgba(10,4,25,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, 64, 64);
    });
}

/** Gentle ambient motion: crowd sway, banner flutter, drifting motes. */
export function animateStage(set, t) {
    const m = new THREE.Matrix4();
    const crowd = set.crowd;
    crowd.userData.base.forEach(([x, y, z, p], i) => {
        m.makeTranslation(x, y + Math.max(0, Math.sin(t * 2.2 + p)) * 0.05, z);
        crowd.setMatrixAt(i, m);
    });
    crowd.instanceMatrix.needsUpdate = true;
    for (const b of set.banners) b.rotation.z = Math.sin(t * 1.3 + b.userData.phase) * 0.035;
    set.motes.position.y = Math.sin(t * 0.3) * 0.15;
    set.motes.rotation.y = t * 0.02;

    for (const b of set.braziers) {
        const f = 0.85 + Math.sin(t * 13 + b.phase) * 0.08 + Math.sin(t * 7.3 + b.phase * 2) * 0.07;
        b.flames.forEach((sp, k) => {
            sp.scale.set(0.42 - k * 0.09, (0.55 - k * 0.1) * f, 1);
            sp.position.x = Math.sin(t * 5 + k + b.phase) * 0.02;
        });
        if (b.light) b.light.intensity = 3.2 * f;
    }
    const e = set.embers, p = e.points.geometry.attributes.position;
    e.seeds.forEach(([x, z, o, sp], i) => {
        const k = ((t * sp + o) % 3) / 3;
        p.setXYZ(i, x + Math.sin(t + i) * 0.15 * k, 1.5 + k * 2.6, z + Math.cos(t * 0.8 + i) * 0.15 * k);
    });
    p.needsUpdate = true;
    for (const sh of set.shafts) sh.material.uniforms.uPower.value = set.spots[0].intensity / 70;
    for (const f of set.flashes) {
        f.next -= 1 / 60;
        if (f.next <= 0 && f.life <= 0) {
            const base = set.crowd.userData.base[(Math.random() * set.crowd.userData.base.length) | 0];
            f.sp.position.set(base[0], base[1] + 0.25, base[2]);
            f.life = 1;
            f.next = 0.6 + Math.random() * 2.5;
        }
        if (f.life > 0) { f.life -= 0.12; f.sp.material.opacity = Math.max(0, f.life); }
    }
}
