// Arena sounds made on the fly with Web Audio, so there is nothing to download.
// Muted until someone turns sound on.
export class Sound {
    constructor() {
        this.muted = true;
        this.ctx = null;
    }

    unlock() {
        if (this.ctx || this.muted) return;
        const AC = window.AudioContext || window.webkitAudioContext;
        if (AC) this.ctx = new AC();
    }

    setMuted(m) {
        this.muted = m;
        if (!m) { this.unlock(); this.ctx?.resume?.(); }
    }

    close() { this.ctx?.close?.(); this.ctx = null; }

    tone(freq, { type = 'sine', dur = 0.25, gain = 0.15, to = null, delay = 0 } = {}) {
        if (this.muted || !this.ctx) return;
        const c = this.ctx, t = c.currentTime + delay;
        const o = c.createOscillator(), g = c.createGain();
        o.type = type;
        o.frequency.setValueAtTime(freq, t);
        if (to) o.frequency.exponentialRampToValueAtTime(to, t + dur);
        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(gain, t + 0.02);
        g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
        o.connect(g).connect(c.destination);
        o.start(t);
        o.stop(t + dur + 0.05);
    }

    noise(dur = 0.3, { gain = 0.2, filter = 900, delay = 0 } = {}) {
        if (this.muted || !this.ctx) return;
        const c = this.ctx, t = c.currentTime + delay;
        const buf = c.createBuffer(1, Math.ceil(c.sampleRate * dur), c.sampleRate);
        const d = buf.getChannelData(0);
        for (let i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * (1 - i / d.length);
        const src = c.createBufferSource(), g = c.createGain(), f = c.createBiquadFilter();
        src.buffer = buf;
        f.type = 'lowpass';
        f.frequency.value = filter;
        g.gain.value = gain;
        src.connect(f).connect(g).connect(c.destination);
        src.start(t);
    }

    hum() { this.tone(110, { type: 'triangle', dur: 1.2, gain: 0.05, to: 165 }); }
    land() { this.tone(90, { dur: 0.25, gain: 0.25, to: 45 }); this.noise(0.18, { gain: 0.12, filter: 500 }); }
    charge() { this.tone(220, { type: 'sawtooth', dur: 0.45, gain: 0.05, to: 880 }); }
    zap() { this.noise(0.5, { gain: 0.18, filter: 4500 }); this.tone(1200, { type: 'square', dur: 0.3, gain: 0.03, to: 300 }); }
    whoosh() { this.noise(0.55, { gain: 0.16, filter: 1400 }); }
    impact() { this.tone(70, { dur: 0.4, gain: 0.35, to: 35 }); this.noise(0.35, { gain: 0.25, filter: 1200 }); }
    sparkle() { [1320, 1760, 2093].forEach((f, i) => this.tone(f, { dur: 0.25, gain: 0.05, delay: i * 0.07 })); }
    fanfare() { [523, 659, 784, 1047].forEach((f, i) => this.tone(f, { type: 'triangle', dur: i === 3 ? 0.6 : 0.18, gain: 0.08, delay: i * 0.13 })); }
    rumble() { this.noise(1.2, { gain: 0.1, filter: 220 }); }
}
