// One entry per fighter the arena can render in 3D. Everything the battle
// needs to know about a body lives here, so the same timeline works for any
// lineup: its file, how tall to draw it, how it moves, where its attack comes
// from and what the attack looks like.
//
//   height     world units, tallest point after scaling (podium tops are ~1)
//   hover      resting height above the floor for floaters and fliers
//   style      hop | wings | float  — how it enters, falls and celebrates
//   attack     bolt | stream | orbs — which effect the champion fires
//   color      effect colour
//   mouth      0–1 of height where the attack starts
//   clips      canonical clip names the file has (see public/models/colosseum)
//   attackClip clip to play for the attack and how fast
//   hitClip    clip to play while knocked down / getting up
//   wings      bone-name pattern for wings, when the rig has them
export const ROSTER = {
    pikachu: {
        name: 'Pikachu', file: 'pikachu.glb', height: 1.1, style: 'hop', attack: 'bolt', color: 0xffe14a, mouth: 0.55,
        attackClip: { name: 'attack', timeScale: 1.5 },
    },
    charizard: {
        name: 'Charizard', file: 'charizard.glb', height: 1.45, style: 'wings', attack: 'stream', color: 0xff7a1a, mouth: 0.78,
        hitClip: { name: 'dizzy', timeScale: 1.2 }, wings: /feeler/i,
    },
    mewtwo: {
        name: 'Mewtwo', file: 'mewtwo.glb', height: 1.6, style: 'float', attack: 'orbs', color: 0xb36bff, mouth: 0.62, full: true,
        attackClip: { name: 'attack', timeScale: 1.1 },
    },
    dragonite: {
        name: 'Dragonite', file: 'dragonite.glb', height: 1.55, style: 'wings', attack: 'orbs', color: 0x7ad7ff, mouth: 0.8, full: true,
        attackClip: { name: 'attack', timeScale: 1.1 },
    },
    gengar: {
        name: 'Gengar', file: 'gengar.glb', height: 1.25, hover: 0.15, style: 'float', attack: 'orbs', color: 0x9b4dff, mouth: 0.55,
    },
    eevee: {
        name: 'Eevee', file: 'eevee.glb', height: 1.0, style: 'hop', attack: 'orbs', color: 0xffd38a, mouth: 0.45,
    },
};

// Stand-ins for anyone whose buddy has no 3D model yet. Their own buddy still
// shows everywhere else; the arena labels the stand-in as one.
const STAND_INS = ['pikachu', 'charizard', 'mewtwo', 'dragonite', 'gengar', 'eevee'];

/** @returns {{ slug: string, standIn: boolean }} */
export function fighterFor(buddy, seed) {
    if (buddy && ROSTER[buddy]) return { slug: buddy, standIn: false };
    return { slug: STAND_INS[Math.abs(seed | 0) % STAND_INS.length], standIn: true };
}
