{{-- View Pallet gets one deliberate action bar instead of Filament's conditional action shells. --}}
<style>
body.vx-pallet-view-screen .fi-page-header .fi-header-actions,
body.vx-pallet-view-screen .fi-page-header .fi-header-actions-ctn { display:none !important; }
body.vx-pallet-view-screen .vx-pallet-workflow-bar { display:flex; flex-wrap:wrap; align-items:center; gap:8px; width:100%; margin-top:2px; }
body.vx-pallet-view-screen .vx-pallet-primary-actions { display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
body.vx-pallet-view-screen .vx-pallet-workflow-bar .vx-action-shell { display:block !important; width:auto !important; min-width:0 !important; margin:0 !important; }
body.vx-pallet-view-screen .vx-pallet-workflow-bar .fi-btn,
body.vx-pallet-view-screen .vx-pallet-workflow-bar a,
body.vx-pallet-view-screen .vx-pallet-workflow-bar button { width:auto !important; min-height:42px !important; padding:9px 14px !important; border-radius:9px !important; justify-content:center !important; gap:7px !important; white-space:nowrap !important; font-size:14px !important; font-weight:700 !important; }
body.vx-pallet-view-screen .vx-pallet-primary-actions .fi-btn-label,
body.vx-pallet-view-screen .vx-pallet-primary-actions svg { color:inherit !important; opacity:1 !important; }
body.vx-pallet-view-screen .vx-pallet-more { position:relative; }
body.vx-pallet-view-screen .vx-pallet-more > summary { list-style:none; display:inline-flex; align-items:center; justify-content:center; gap:7px; min-height:42px; padding:9px 14px; border:1px solid #cbd5e1; border-radius:9px; background:#fff; color:#1e293b; cursor:pointer; font-size:14px; font-weight:700; }
body.vx-pallet-view-screen .vx-pallet-more > summary::-webkit-details-marker { display:none; }
html.dark body.vx-pallet-view-screen .vx-pallet-more > summary { background:#182235; border-color:#334155; color:#f8fafc; }
body.vx-pallet-view-screen .vx-pallet-more-menu { position:absolute; z-index:90; top:calc(100% + 7px); right:0; min-width:250px; padding:7px; border:1px solid #dbe3ee; border-radius:11px; background:#fff; box-shadow:0 18px 45px rgba(15,23,42,.17); }
html.dark body.vx-pallet-view-screen .vx-pallet-more-menu { background:#111827; border-color:#334155; }
body.vx-pallet-view-screen .vx-pallet-more-menu .vx-action-shell { width:100% !important; }
body.vx-pallet-view-screen .vx-pallet-more-menu .fi-btn,
body.vx-pallet-view-screen .vx-pallet-more-menu a,
body.vx-pallet-view-screen .vx-pallet-more-menu button { width:100% !important; min-height:40px !important; justify-content:flex-start !important; text-align:left !important; padding:9px 10px !important; border-radius:8px !important; background:transparent !important; color:#334155 !important; box-shadow:none !important; }
html.dark body.vx-pallet-view-screen .vx-pallet-more-menu .fi-btn,
html.dark body.vx-pallet-view-screen .vx-pallet-more-menu a,
html.dark body.vx-pallet-view-screen .vx-pallet-more-menu button { color:#e2e8f0 !important; }
body.vx-pallet-view-screen .fi-page-content { padding-top:10px !important; }
@media(max-width:768px){
 body.vx-pallet-view-screen .vx-pallet-workflow-bar,body.vx-pallet-view-screen .vx-pallet-primary-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;width:100%}
 body.vx-pallet-view-screen .vx-pallet-primary-actions{grid-column:1/-1}
 body.vx-pallet-view-screen .vx-pallet-primary-actions .vx-action-shell:first-child{grid-column:1/-1}
 body.vx-pallet-view-screen .vx-pallet-workflow-bar .vx-action-shell,body.vx-pallet-view-screen .vx-pallet-workflow-bar .fi-btn,body.vx-pallet-view-screen .vx-pallet-workflow-bar a,body.vx-pallet-view-screen .vx-pallet-workflow-bar button{width:100%!important}
 body.vx-pallet-view-screen .vx-pallet-more{grid-column:1/-1;width:100%}
 body.vx-pallet-view-screen .vx-pallet-more>summary{width:100%}
 body.vx-pallet-view-screen .vx-pallet-more-menu{position:fixed;left:10px;right:10px;bottom:calc(env(safe-area-inset-bottom,0px) + 12px);top:auto;width:auto;max-height:55dvh;overflow-y:auto}
}
</style>
<script>
(() => {
 const norm=v=>(v||'').replace(/\s+/g,' ').trim().toLowerCase();
 const isView=()=>/^\/admin\/pallets\/\d+\/?$/.test(location.pathname.toLowerCase());
 let queued=false;
 const build=()=>{
  if(!isView()) return;
  document.body.classList.add('vx-pallet-view-screen','vx-pallet-screen');
  const header=document.querySelector('.fi-page-header');
  const source=header?.querySelector('.fi-header-actions,.fi-header-actions-ctn');
  if(!header||!source) return;
  let bar=header.querySelector('.vx-pallet-workflow-bar');
  if(!bar){bar=document.createElement('div');bar.className='vx-pallet-workflow-bar';bar.innerHTML='<div class="vx-pallet-primary-actions"></div><details class="vx-pallet-more"><summary><span aria-hidden="true">⋮</span> More</summary><div class="vx-pallet-more-menu"></div></details>';header.appendChild(bar);}
  const primary=bar.querySelector('.vx-pallet-primary-actions'), more=bar.querySelector('.vx-pallet-more-menu');
  [...source.children].forEach(shell=>{
   const text=norm(shell.textContent); if(!text||(!shell.querySelector('a')&&!shell.querySelector('button'))) return;
   shell.classList.add('vx-action-shell');
   if(/start receiving|continue receiving|scan item|review & receive|review manifest/.test(text)) primary.appendChild(shell); else more.appendChild(shell);
  });
  const seen=new Set();[...primary.children].forEach(shell=>{const t=norm(shell.textContent);const k=/start receiving|continue receiving/.test(t)?'receive':/scan item/.test(t)?'scan':/review & receive|review manifest/.test(t)?'review':t;if(seen.has(k))shell.remove();else seen.add(k);});
 };
 const refresh=()=>{if(queued)return;queued=true;requestAnimationFrame(()=>{queued=false;build();});};
 document.addEventListener('DOMContentLoaded',refresh);document.addEventListener('livewire:navigated',refresh);document.addEventListener('livewire:initialized',refresh);new MutationObserver(refresh).observe(document.documentElement,{childList:true,subtree:true});refresh();
})();
</script>