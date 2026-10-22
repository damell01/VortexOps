// The leaderboard stays functional without WebGL or without this optional enhancement.
// Warm the 3D scene after dashboard paint, but play only when scrolled into view.
const mounted = new WeakSet();
function supportsArena() {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches || matchMedia('(max-width: 640px)').matches) return false;
  try { const canvas = document.createElement('canvas'); return !!(canvas.getContext('webgl2') || canvas.getContext('webgl')); }
  catch { return false; }
}
function mount(root) {
  if (mounted.has(root)) return;
  mounted.add(root);
  const stage = root.querySelector('[data-vxl-3d-stage]');
  const data = root.querySelector('[data-vxl-3d-lineup]');
  if (!stage || !data) return;
  let rows;
  try { rows = JSON.parse(data.textContent); } catch { return; }
  if (!Array.isArray(rows) || rows.length === 0) return;
  const fallback = () => {
    root.classList.remove('vxl-3d-active', 'vxl-3d-settled');
    if (!matchMedia('(prefers-reduced-motion: reduce)').matches) window.Alpine?.$data(root)?.play?.();
  };
  let arena = null, promise = null, playing = false, played = false, disposed = false;
  let observer = null, removal = null;
  const cleanup = () => {
    if (disposed) return;
    disposed = true;
    observer?.disconnect();
    removal?.disconnect();
    document.removeEventListener('livewire:navigating', cleanup);
    arena?.destroy();
    arena = null;
  };
  if (!supportsArena()) {
    observer = new IntersectionObserver(([entry]) => {
      if (entry.isIntersecting) { observer.disconnect(); fallback(); }
    }, { threshold: .3 });
    observer.observe(root);
    return;
  }
  // Hide the static podium as soon as 3D is supported, before preload completes.
  root.classList.add('vxl-3d-active');
  root.classList.remove('vxl-3d-settled');
  const prepare = () => {
    if (!promise) promise = (async () => {
      const { Arena } = await import('./colosseum/arena.js');
      if (disposed || !root.isConnected) return null;
      const instance = new Arena(stage, rows, {
        compact: true,
        onState: state => {
          if (state === 'settled') {
            played = true;
            playing = false;
            root.classList.add('vxl-3d-settled');
          }
        },
      });
      arena = instance;
      instance.setMuted(true);
      await instance.prepare();
      if (disposed || !root.isConnected) return null;
      return instance;
    })().catch(error => {
      console.warn('[leaderboard 3D] prepare:', error);
      arena?.destroy(); arena = null;
      throw error;
    });
    return promise;
  };
  const play = async (replay = false) => {
    if (disposed || playing || (played && !replay)) return;
    playing = true;
    // Hide results throughout the battle, including on replay.
    root.classList.remove('vxl-3d-settled');
    try {
      const ready = await prepare();
      if (!ready || disposed || !root.isConnected) return;
      root.classList.add('vxl-3d-active');
      await ready.play('quick');
    } catch (error) {
      console.warn('[leaderboard 3D] falling back:', error);
      fallback();
    } finally {
      // The arena's settled state clears playing after the three-second timeline.
      if (!arena) playing = false;
    }
  };
  root.vxlReplay3d = () => {
    if (!arena || playing || disposed) return false;
    play(true);
    return true;
  };
  // Preload on page arrival, without blocking login or the initial page paint.
  const warm = () => { if (!disposed && root.isConnected) prepare().catch(() => {}); };
  if ('requestIdleCallback' in window) requestIdleCallback(warm, { timeout: 1200 });
  else setTimeout(warm, 150);
  observer = new IntersectionObserver(([entry]) => {
    if (!entry.isIntersecting) return;
    observer.disconnect();
    play();
  }, { threshold: .3 });
  observer.observe(root);
  document.addEventListener('livewire:navigating', cleanup);
  removal = new MutationObserver(() => {
    if (!root.isConnected) cleanup();
  });
  removal.observe(document.body, { childList: true, subtree: true });
}
function scan() { document.querySelectorAll('[data-vxl-3d]').forEach(mount); }
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan, { once: true });
else scan();
document.addEventListener('livewire:navigated', scan);
document.addEventListener('livewire:init', () => window.Livewire?.hook('morph.updated', scan));
