// The "film" look on desktop: bloom on bright things (trim, glows, fire,
// lightning), then one pass for vignette, grain and an impact colour fringe.
// Phones skip this and render straight to the screen.
import * as THREE from 'three';
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js';
import { ShaderPass } from 'three/examples/jsm/postprocessing/ShaderPass.js';
import { OutputPass } from 'three/examples/jsm/postprocessing/OutputPass.js';

const FilmShader = {
    uniforms: {
        tDiffuse: { value: null },
        uTime: { value: 0 },
        uVignette: { value: 0.9 },
        uGrain: { value: 0.045 },
        uFringe: { value: 0 },
    },
    vertexShader: /* glsl */`
        varying vec2 vUv;
        void main() { vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }`,
    fragmentShader: /* glsl */`
        uniform sampler2D tDiffuse;
        uniform float uTime, uVignette, uGrain, uFringe;
        varying vec2 vUv;
        float hash(vec2 p) { return fract(sin(dot(p, vec2(12.9898, 78.233)) + uTime * 61.7) * 43758.5453); }
        void main() {
            vec2 c = vUv - 0.5;
            vec2 off = c * uFringe * 0.012;
            vec4 col = texture2D(tDiffuse, vUv);
            col.r = texture2D(tDiffuse, vUv + off).r;
            col.b = texture2D(tDiffuse, vUv - off).b;
            float v = smoothstep(0.85, 0.2, length(c * vec2(1.0, 1.25)));
            col.rgb *= mix(1.0 - uVignette * 0.55, 1.0, v);
            col.rgb += (hash(vUv * 900.0) - 0.5) * uGrain;
            gl_FragColor = col;
        }`,
};

export class Post {
    constructor(renderer, scene, camera) {
        this.composer = new EffectComposer(renderer);
        this.composer.addPass(new RenderPass(scene, camera));
        this.bloom = new UnrealBloomPass(new THREE.Vector2(512, 256), 0.32, 0.55, 0.9);
        this.composer.addPass(this.bloom);
        this.film = new ShaderPass(FilmShader);
        this.composer.addPass(this.film);
        this.composer.addPass(new OutputPass());
    }

    setSize(w, h, ratio) {
        this.composer.setPixelRatio(ratio);
        // Sizes every pass to the canvas; the bloom pass halves its own targets.
        this.composer.setSize(w, h);
    }

    render(time, { bloom = 0.55, fringe = 0 } = {}) {
        this.bloom.strength = bloom;
        this.film.uniforms.uTime.value = time % 100;
        this.film.uniforms.uFringe.value = fringe;
        this.composer.render();
    }

    dispose() { this.composer.dispose(); }
}
