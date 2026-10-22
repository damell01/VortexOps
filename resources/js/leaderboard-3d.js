// Render the original 3D colosseum only after the leaderboard scrolls into view.
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
    if (!stage || !lineupNode || !canRender()) return;
    let arena, started = false, loading = false, destroyed = false;
    const readLineup = () => { try { return JSON.parse(lineupNode.textContent); } catch { return []; } };
    const start = async (replay = false) => {
      if (loading || destroyed) return;
      if (started && !replay) return;
      loading = true;
      try {
        if (!arena) {
          const { Arena } = await import('./colosseum/arena.js');
          if (destroyed || !root.isConnected) return;
          arena = new Arena(stage, readLineup(), {
            base: '/models/colosseum/', compact: true,
            onState: state => { if (state === 'settled') root.classList.add('vxl-3d-settled'); },
          });
          arena.setMuted(true);
        }
        root.classList.add('vxl-3d-active');
        await arena.play('quick');
        started = true;
      } catch (error) {
        console.warn('[leaderboard 3D] CSS fallback:', error);
        root.classList.remove('vxl-3d-active');
        arena?.destroy(); arena = null;
        root.querySelector('.vxl-replay')?.click();
      } finally { loading = false; }
    };
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
