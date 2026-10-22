// Prewarm the original 3D colosseum after page load; play only on scroll.
// Real names, money and links stay in the HTML podium; WebGL is decorative.
const mounted = new WeakSet();
function canRender() {
  if (matchMedia('(prefers-reduced-motion: reduce)').matches || matchMedia('(max-width: 640px)').matches) return false;
  try { const c = document.createElement('canvas'); return !!(c.getContext('webgl2') || c.getContext('webgl')); } catch { return false; }
}
function scan() {
  document.querySelectorAll('[data-vxl-3d]').forEach(root => {
    if (mounted.has(root)) return;
    mounted.add(root);
    const stage = root.querySelector('[data-vxl-3d-stage]');
    const lineupNode = root.querySelector('[data-vxl-3d-lineup]');
    if (!stage || !lineupNode) return;
    if (!canRender()) {
      const io = new IntersectionObserver(entries => {
        if (!entries[0]?.isIntersecting) return;
        io.disconnect();
        if (!matchMedia('(prefers-reduced-motion: reduce)').matches) window.Alpine?.$data(root)?.play?.();
      }, { threshold: .3 });
      io.observe(root);
      return;
    }
    let arena, started = false, loading = false, destroyed = false, preparing = null;
    const readLineup = () => { try { return JSON.parse(lineupNode.textContent); } catch { return []; } };
    // Warm models and shaders on page arrival, but don't start the battle.
    // Promise shared with the viewport trigger so no duplicate downloads occur.
    const prepare = () => preparing ??= (async () => {
      const { Arena } = await import('./colosseum/arena.js');
      if (destroyed || !root.isConnected) return null;
      const instance = new Arena(stage, readLineup(), {
        base: '/models/colosseum/', compact: true,
        onState: state => { if (state === 'settled') root.classList.add('vxl-3d-settled'); },
      });
      arena = instance;
      instance.setMuted(true);
      await instance.prepare();
      if (destroyed || !root.isConnected) return null;
      return instance;
    })().catch(error => {
      console.warn('[leaderboard 3D] preload failed:', error);
      arena?.destroy(); arena = null;
      throw error;
    });
    const start = async (replay = false) => {
      if (loading || destroyed || (started && !replay)) return;
      loading = true;
      try {
        const ready = await prepare();
        if (!ready || destroyed || !root.isConnected) return;
        root.classList.add('vxl-3d-active');
        await ready.play('quick');
        started = true;
      } catch (error) {
        console.warn('[leaderboard 3D] CSS fallback:', error);
        root.classList.remove('vxl-3d-active');
        window.Alpine?.$data(root)?.play?.();
      } finally { loading = false; }
    };
    // Start downloading as soon as the page is ready, without blocking navigation.
    // Idle callback lets more important dashboard work paint first.
    if ('requestIdleCallback' in window) requestIdleCallback(() => { if (!destroyed) prepare().catch(() => {}); }, { timeout: 1200 });
    else setTimeout(() => { if (!destroyed) prepare().catch(() => {}); }, 150);
    root.vxlReplay3d = () => { if (!arena || loading) return false; start(true); return true; };
    const observer = new IntersectionObserver(entries => {
      if (!entries[0]?.isIntersecting) return;
      observer.disconnect();
      start();
    }, { threshold: .3 });
    observer.observe(root);
    const cleanup = () => { destroyed = true; observer.disconnect(); arena?.destroy(); arena = null; };
    document.addEventListener('livewire:navigating', cleanup, { once: true });
    // Livewire replaces the podium on period changes; release old WebGL resources.
    const removal = new MutationObserver(() => {
      if (!root.isConnected) { cleanup(); removal.disconnect(); }
    });
    removal.observe(document.body, { childList: true, subtree: true });
  });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
else scan();
document.addEventListener('livewire:navigated', scan);
document.addEventListener('livewire:init', () => { window.Livewire?.hook('morph.updated', scan); });
