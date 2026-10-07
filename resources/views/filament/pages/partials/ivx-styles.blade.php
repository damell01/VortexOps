{{--
    Shared look for the inventory workspace (Overview, Stock Status, Age,
    Health, Recent Activity, item detail): mobile-first cards, segmented tabs,
    KPI tiles, status pills and list rows, matching the inventory mockups.
    Included once per page; every rule is scoped under .ivx.
--}}
<style>
.ivx{width:100%;--line:#e7e9f0;--soft:#f7f8fb;--muted:#64748b;--faint:#94a3b8;--text:#0f172a;--p:var(--primary-600,#7c3aed);--p-soft:color-mix(in srgb,var(--p) 9%,transparent);--green:#16a34a;--amber:#f59e0b;--red:#ef4444;max-width:1180px;margin:0 auto;display:grid;gap:14px;min-width:0;color:var(--text)}
.dark .ivx{--line:#253247;--soft:#111c2f;--muted:#94a3b8;--faint:#64748b;--text:#e5e7eb}
.ivx *{min-width:0}
.ivx a{color:inherit;text-decoration:none}
.ivx .ivx-card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.dark .ivx .ivx-card{background:#0f172a}
.ivx .ivx-pad{padding:16px}
.ivx .ivx-h{font-size:.95rem;font-weight:800;letter-spacing:-.005em}
.ivx .ivx-sub{font-size:.76rem;color:var(--muted)}
.ivx .ivx-tabs{display:flex;gap:4px;padding:4px;border:1px solid var(--line);border-radius:12px;background:#fff;overflow-x:auto;scrollbar-width:none}
.dark .ivx .ivx-tabs{background:#0f172a}
.ivx .ivx-tabs::-webkit-scrollbar{display:none}
.ivx .ivx-tabs>*{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:0 14px;border-radius:9px;font-size:.8rem;font-weight:700;color:var(--muted);white-space:nowrap}
.ivx .ivx-tabs>.on{background:var(--p);color:#fff;box-shadow:0 2px 8px color-mix(in srgb,var(--p) 35%,transparent)}
.ivx .ivx-kpis{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.ivx .ivx-kpis.k3{grid-template-columns:repeat(3,minmax(0,1fr))}
.ivx .ivx-kpis.k4{grid-template-columns:repeat(4,minmax(0,1fr))}
.ivx .ivx-kpi{display:flex;flex-direction:column;gap:4px;padding:14px;border:1px solid var(--line);border-radius:14px;background:#fff}
.dark .ivx .ivx-kpi{background:#0f172a}
.ivx a.ivx-kpi:hover,.ivx button.ivx-kpi:hover{border-color:var(--p)}
.ivx .ivx-kpi.on{border-color:var(--p);box-shadow:inset 0 0 0 1px var(--p)}
.ivx .ivx-kpi .v{font-size:1.35rem;font-weight:800;line-height:1.1;font-variant-numeric:tabular-nums;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ivx .ivx-kpi .l{font-size:.72rem;color:var(--muted);display:flex;align-items:center;gap:6px}
.ivx .ivx-kpi.center{align-items:center;text-align:center}
.ivx .ivx-ic{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:var(--p-soft);color:var(--p);flex:none}
.ivx .ivx-ic.green{background:#dcfce7;color:var(--green)}.ivx .ivx-ic.amber{background:#fef3c7;color:#d97706}.ivx .ivx-ic.red{background:#fee2e2;color:var(--red)}
.ivx .ivx-ic svg{width:20px;height:20px}
.ivx .ivx-dot{display:inline-block;width:8px;height:8px;border-radius:999px;flex:none}
.ivx .ivx-dot.green{background:var(--green)}.ivx .ivx-dot.amber{background:var(--amber)}.ivx .ivx-dot.red{background:var(--red)}.ivx .ivx-dot.violet{background:var(--p)}
.ivx .ivx-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;font-size:.68rem;font-weight:700;white-space:nowrap}
.ivx .ivx-pill.green{background:#dcfce7;color:#15803d}.ivx .ivx-pill.amber{background:#fef3c7;color:#b45309}.ivx .ivx-pill.red{background:#fee2e2;color:#dc2626}.ivx .ivx-pill.violet{background:var(--p-soft);color:var(--p)}.ivx .ivx-pill.gray{background:#f1f5f9;color:#475569}
.dark .ivx .ivx-pill.green{background:#052e1a;color:#86efac}.dark .ivx .ivx-pill.amber{background:#3a2a06;color:#fcd34d}.dark .ivx .ivx-pill.red{background:#451a1a;color:#fca5a5}.dark .ivx .ivx-pill.gray{background:#1e293b;color:#cbd5e1}
.ivx .ivx-up{display:inline-flex;align-items:center;gap:2px;padding:2px 7px;border-radius:999px;background:#dcfce7;color:#15803d;font-size:.66rem;font-weight:800}
.ivx .ivx-up.down{background:#fee2e2;color:#dc2626}
.ivx .ivx-search{position:relative;flex:1}
.ivx .ivx-search svg{position:absolute;left:12px;top:50%;width:16px;height:16px;transform:translateY(-50%);color:var(--faint)}
.ivx .ivx-search input,.ivx .ivx-input,.ivx select.ivx-input{width:100%;min-height:42px;padding:0 12px;border:1px solid var(--line);border-radius:12px;background:#fff;font-size:.85rem;color:inherit}
.ivx .ivx-search input{padding-left:36px}
.dark .ivx .ivx-search input,.dark .ivx .ivx-input{background:#0f172a}
.ivx .ivx-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:42px;padding:0 14px;border:1px solid var(--line);border-radius:12px;background:#fff;font-size:.82rem;font-weight:700;color:inherit;white-space:nowrap}
.dark .ivx .ivx-btn{background:#0f172a}
.ivx .ivx-btn.ic{width:42px;padding:0}
.ivx .ivx-btn.primary{background:var(--p);border-color:var(--p);color:#fff}
.ivx .ivx-btn svg{width:16px;height:16px}
.ivx .ivx-list>*+*{border-top:1px solid var(--line)}
.ivx .ivx-row{display:flex;align-items:center;gap:12px;padding:12px 14px}
.ivx a.ivx-row:hover{background:var(--soft)}
.ivx .ivx-thumb{width:46px;height:46px;border-radius:10px;object-fit:cover;background:var(--soft);border:1px solid var(--line);flex:none;display:grid;place-items:center;color:var(--faint)}
.ivx .ivx-thumb svg{width:20px;height:20px}
.ivx .ivx-name{font-size:.86rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ivx .ivx-meta{font-size:.7rem;color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ivx .ivx-right{margin-left:auto;display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex:none;text-align:right}
.ivx .ivx-chev{width:16px;height:16px;color:var(--faint);flex:none}
.ivx .ivx-bar{height:8px;border-radius:999px;background:var(--soft);overflow:hidden;display:flex}
.ivx .ivx-bar>i{display:block;height:100%}
.ivx .ivx-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}
.ivx .ivx-link{font-size:.76rem;font-weight:700;color:var(--p)}
.ivx .ivx-empty{padding:36px 16px;text-align:center;font-size:.84rem;color:var(--muted)}
.ivx .ivx-grid2{display:grid;grid-template-columns:minmax(0,1fr);gap:14px}
@media(min-width:900px){.ivx .ivx-grid2{grid-template-columns:repeat(2,minmax(0,1fr))}.ivx .ivx-kpis.wide4{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:520px){.ivx .ivx-kpis.k4{grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}.ivx .ivx-kpis.k4 .ivx-kpi{padding:10px 4px}.ivx .ivx-kpis.k4 .v{font-size:1.05rem}.ivx .ivx-kpis.k4 .l{font-size:.62rem}.ivx .ivx-kpis.k4 .ivx-ic{width:28px;height:28px;border-radius:8px}.ivx .ivx-kpis.k4 .ivx-ic svg{width:15px;height:15px}.ivx .ivx-kpis.k3 .ivx-kpi{padding:12px 10px}}
</style>
