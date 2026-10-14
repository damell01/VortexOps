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
