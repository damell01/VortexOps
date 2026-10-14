<style>
.vx-report-hub{max-width:1600px;width:100%;margin:auto;display:grid;gap:22px;color:inherit}
.vx-report-hub .rh-card{background:var(--vxw-card,#fff);border:1px solid #e5e7eb;border-radius:18px;padding:24px;min-width:0}
.dark .vx-report-hub .rh-card{background:#111827;border-color:#374151}
.vx-report-hub .rh-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
.vx-report-hub .rh-card strong{display:block;font-size:1.8rem;font-weight:800;margin-top:12px;overflow-wrap:anywhere}
.vx-report-hub p,.vx-report-hub .rh-card>span{color:#64748b;font-size:.9rem;line-height:1.6}
.dark .vx-report-hub p,.dark .vx-report-hub .rh-card>span{color:#94a3b8}
.vx-report-hub h2{font-size:1.15rem;font-weight:800;margin-bottom:14px}
.vx-report-hub .rh-toolbar{display:flex;flex-wrap:wrap;gap:14px;align-items:center}
.vx-report-hub input,.vx-report-hub select{display:block;min-height:44px;border:1px solid #cbd5e1;border-radius:10px;background:transparent;margin-top:4px;max-width:100%}
.vx-report-hub .rh-button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 18px;border:1px solid #d1d5db;border-radius:10px;font-weight:700;font-size:.9rem;gap:8px}
.vx-report-hub .primary{background:#7c3aed;color:#fff;border-color:#7c3aed}
.vx-report-hub .rh-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 0;border-bottom:1px solid #e5e7eb;min-width:0}
.vx-report-hub .rh-row:last-child{border:0}
.vx-report-hub .rh-note{padding:16px;border-radius:12px;background:#f5f3ff;color:#6d28d9}
.dark .vx-report-hub .rh-note{background:#2e1065;color:#ddd6fe}
.vx-report-hub .rh-link{display:block;transition:box-shadow .15s}.vx-report-hub .rh-link:hover{box-shadow:0 6px 20px #0f172a12}
.vx-report-hub svg{width:24px;height:24px;color:#7c3aed}
.vx-streamer-report .vx-person-card{padding:22px!important;border-radius:18px!important}.vx-streamer-report .vx-person-card strong{font-size:1.05rem}.vx-streamer-report .vx-person-card span{font-size:.85rem}
.rh-two{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:20px}.rh-trend{height:200px;display:flex;align-items:end;gap:10px;margin:20px 0}.rh-trend-item{flex:1;min-width:0;display:flex;flex-direction:column;justify-content:end;height:100%;gap:8px;text-align:center}.rh-trend-bar{background:#8b5cf6;border-radius:7px 7px 0 0;min-height:4px}.rh-trend-item small{font-size:.7rem;color:#64748b}.rh-trend-item b{font-size:.75rem}
@media(max-width:900px){.rh-two{grid-template-columns:1fr}}
/* Apply the report workspace density consistently without changing report data. */
.fi-main:has(.vx-report),.fi-main:has(.vx-report-hub),.fi-main:has([data-vx-page="profit-loss"]),.fi-main:has(.vx-ns){max-width:1680px!important;width:100%}
.vx-report,.vx-business-report,.vx-ns{max-width:1600px!important;width:100%!important;margin-left:auto;margin-right:auto}
.vx-report h2{font-size:1.2rem!important}.vx-report .vx-kpi-value{font-size:1.65rem!important}
.vx-report .vx-kpi,.vx-report .vx-report-panel{padding:20px!important;border-radius:18px!important}
.vx-report .vx-table td{font-size:.9rem!important;padding:16px!important}.vx-report .vx-table th{font-size:.75rem!important}
@media(max-width:700px){.vx-report-hub .rh-card{padding:18px}.vx-report-hub .rh-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.vx-report-hub .rh-row{align-items:flex-start;flex-wrap:wrap}.vx-report-hub .rh-card strong{font-size:1.4rem}.vx-report-hub .rh-toolbar>*{flex:1 1 140px}}
@media(prefers-reduced-motion:reduce){.vx-report-hub .rh-link{transition:none}}
</style>
<style>
/* Mockup controls remain native, preserving keyboard and mobile picker support. */
:is(.vx-report,.vx-report-hub,[data-vx-page="profit-loss"]) :is(select,input[type="date"],input[type="search"],input[type="text"]){min-height:44px;border:1px solid #dce2ee!important;border-radius:9px!important;background-color:#fff;color:#18213c;padding:10px 14px;font-size:13px;box-shadow:0 1px 2px #17213d04;max-width:100%}
:is(.vx-report,.vx-report-hub,[data-vx-page="profit-loss"]) select{appearance:none;padding-right:38px!important;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none' stroke='%2357627e' stroke-width='1.7'%3E%3Cpath d='m5 7 5 5 5-5'/%3E%3C/svg%3E");background-position:right 12px center;background-repeat:no-repeat;background-size:18px}
:is(.vx-report,.vx-report-hub,[data-vx-page="profit-loss"]) :is(select,input,summary,button,a):focus-visible{outline:3px solid #c4b5fd;outline-offset:2px}
.dark :is(.vx-report,.vx-report-hub,[data-vx-page="profit-loss"]) :is(select,input[type="date"],input[type="search"],input[type="text"]){background-color:#111827;color:#e2e8f0;border-color:#374151!important;color-scheme:dark}
.vx-report :is(.vx-r-card,.vx-person-card),.vx-report-hub .rh-card{border-color:#dfe5ef!important;border-radius:12px!important;box-shadow:0 3px 15px #17213d04}
.vx-report .vx-quick-reports{display:flex;overflow-x:auto;gap:4px}.vx-report .vx-quick{background:transparent!important;border:0!important;border-radius:0!important;padding:13px 18px!important;min-width:max-content!important;box-shadow:none!important;border-bottom:3px solid transparent!important}.vx-report .vx-quick.active{border-bottom-color:#7738f6!important;color:#6324ed}.vx-report .vx-quick-icon,.vx-report .vx-quick-sub{display:none!important}.vx-report .vx-quick-title{font-size:13px}
.vx-streamer-report .vx-streamer-cards{border:1px solid #dfe5ef;background:var(--vx-surface,#fff);border-radius:12px;padding:20px}.vx-streamer-report .vx-person-card{padding:16px!important;box-shadow:none!important}.vx-streamer-report .vx-person-money,.vx-streamer-report .vx-person-foot{display:grid;grid-template-columns:1fr!important;gap:0!important}.vx-streamer-report :is(.vx-person-money,.vx-person-foot)>div{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #edf0f6;padding:10px 0;gap:10px}.vx-streamer-report .vx-person-money strong,.vx-streamer-report .vx-person-foot strong{font-size:13px!important}.vx-streamer-report .vx-person-money span,.vx-streamer-report .vx-person-foot span{font-size:11px!important}.vx-streamer-report .vx-mini-track{display:none}.vx-streamer-report .vx-avatar{border-radius:50%;background:#e9ddff;color:#6633cd}.vx-top-streamers{border:1px solid #dfe5ef;border-radius:12px;padding:22px;background:var(--vx-surface,#fff)}.vx-top-streamers h3{font-weight:750;margin-bottom:20px}.vx-top-streamer-row{display:grid;grid-template-columns:140px minmax(0,1fr) 100px;gap:18px;align-items:center;margin:14px 0;font-size:13px}.vx-top-streamer-track{background:#f1f3f8;border-radius:4px;height:19px;overflow:hidden}.vx-top-streamer-track i{display:block;height:100%;background:linear-gradient(90deg,#a58aef,#8756f7);border-radius:4px}
@media(min-width:1400px){.vx-streamer-report [data-vx-mobile-page]{grid-template-columns:repeat(6,minmax(0,1fr))!important}.vx-business-report .vx-report-launchers{grid-template-columns:repeat(6,minmax(0,1fr))!important}}
@media(max-width:600px){.vx-top-streamer-row{grid-template-columns:90px minmax(0,1fr) 75px;gap:8px;font-size:11px}.vx-streamer-report .vx-streamer-cards{padding:14px}.vx-report select{width:100%}}
</style>
<style>
.vx-profit-main{display:grid;grid-template-columns:minmax(0,2.7fr) minmax(280px,1fr);gap:20px;align-items:start}.vx-profit-checklist{padding:22px}.vx-profit-check{display:flex;align-items:start;gap:14px;padding:22px 0;border-bottom:1px solid var(--vxw-border,#e2e8f0);font-size:14px}.vx-profit-check p{font-size:13px;color:var(--vxw-muted,#64748b);margin-top:5px}.vx-profit-check-ring{height:25px;width:25px;border:2px solid #e59a0c;border-radius:50%;flex:none}.vx-profit-checklist>p{margin-top:12px}.vx-profit-main .vxw-card{border-radius:12px}
[data-vx-page="profit-loss"]>.vxw-card:has(.vxw-stats){background:transparent;border:0;box-shadow:none}[data-vx-page="profit-loss"] .vxw-stats{gap:14px;background:transparent}[data-vx-page="profit-loss"] .vxw-stat{background:var(--vxw-surface,#fff);border:1px solid var(--vxw-border,#dfe5ef);border-radius:12px;padding:20px}
@media(max-width:900px){.vx-profit-main{grid-template-columns:1fr}}
</style>
