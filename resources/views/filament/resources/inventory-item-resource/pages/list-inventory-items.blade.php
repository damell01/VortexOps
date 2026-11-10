<x-filament-panels::page>
<div x-data="{ pendingStock: null }" x-on:livewire:commit.window="pendingStock = null">
<style>
.vx-health-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.vx-health{border:1px solid var(--vx-border,rgb(229 231 235));border-radius:14px;padding:14px;box-shadow:0 1px 2px rgba(15,23,42,.04);text-align:left;background:white;transition:.15s}.dark .vx-health{background:#101827;border-color:#263248}.vx-health:hover,.vx-health.active{border-color:#7c3aed;box-shadow:0 0 0 1px #7c3aed}.vx-health strong{display:block;font-size:1.65rem}.vx-health{min-height:92px}.vx-health-label{font-weight:700}.vx-health-meta{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:3px}.vx-health-label{font-size:.76rem;color:var(--vx-muted,#556070)}.vx-health-percent{font-size:.72rem;font-weight:700;color:#7c3aed}
.vx-inv-toolbar{display:flex;gap:.65rem;align-items:center;flex-wrap:wrap}.vx-inv-search{flex:1;min-width:220px}.vx-inv-input{width:100%;min-height:44px;border:1px solid rgb(209 213 219);border-radius:.75rem;background:white;padding:.65rem .8rem;font-size:.875rem}.dark .vx-inv-input{background:rgb(17 24 39);border-color:rgb(75 85 99);color:white}.vx-view-toggle{display:inline-flex;padding:3px;border:1px solid rgb(229 231 235);border-radius:.75rem;background:rgb(249 250 251);flex-shrink:0}.dark .vx-view-toggle{background:rgb(17 24 39);border-color:rgb(55 65 81)}.vx-view-btn{min-height:38px;border-radius:.55rem;padding:.45rem .8rem;font-size:.76rem;font-weight:800;color:rgb(107 114 128);display:inline-flex;align-items:center;justify-content:center;gap:.35rem}.vx-view-btn.active{background:white;color:rgb(17 24 39);box-shadow:0 1px 3px rgba(15,23,42,.08)}.dark .vx-view-btn.active{background:rgb(31 41 55);color:white}
.vx-catalog-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.vx-product-card{overflow:hidden;border:1px solid rgb(229 231 235);border-radius:1rem;background:white;transition:.15s ease;display:flex;flex-direction:column;min-width:0}.vx-product-card:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(15,23,42,.08);border-color:rgb(191 219 254)}.dark .vx-product-card{background:rgb(17 24 39);border-color:rgb(55 65 81)}.vx-product-link{display:block;color:inherit;text-decoration:none}.vx-product-image{aspect-ratio:1/1;background:rgb(249 250 251);display:flex;align-items:center;justify-content:center;overflow:hidden;border-bottom:1px solid rgb(243 244 246)}.dark .vx-product-image{background:rgb(31 41 55);border-color:rgb(55 65 81)}.vx-product-image img{width:100%;height:100%;object-fit:contain;padding:.65rem}.vx-product-body{padding:.8rem;display:flex;flex-direction:column;gap:.55rem;flex:1}.vx-product-title{font-weight:750;font-size:.875rem;line-height:1.2rem;color:rgb(17 24 39);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.dark .vx-product-title{color:white}.vx-product-meta{font-size:.7rem;color:rgb(107 114 128);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vx-product-row{display:flex;align-items:center;justify-content:space-between;gap:.5rem}.vx-stock{display:inline-flex;align-items:center;border-radius:999px;padding:.25rem .5rem;font-size:.67rem;font-weight:800}.vx-stock-in{background:rgb(236 253 245);color:rgb(4 120 87)}.vx-stock-low{background:rgb(255 247 237);color:rgb(194 65 12)}.vx-stock-out{background:rgb(254 242 242);color:rgb(185 28 28)}.vx-type{display:inline-flex;border-radius:999px;padding:.2rem .45rem;font-size:.64rem;font-weight:750;background:rgb(243 244 246);color:rgb(75 85 99)}.vx-card-metrics{display:grid;grid-template-columns:64px minmax(0,1fr);gap:8px;align-items:stretch}.vx-card-metrics .vx-cost-metrics{grid-template-columns:1fr}.vx-qty-metric{display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:.7rem;background:#ecfdf5;color:#047857;padding:.45rem .35rem}.vx-qty-metric.out{background:#f8fafc;color:#64748b}.vx-qty-metric.low{background:#fff7ed;color:#c2410c}.vx-cost-metrics{display:grid;grid-template-columns:1fr 1fr;align-items:center;border:1px solid #eef2f7;border-radius:.7rem;background:#f8fafc;overflow:hidden}.vx-cost-metric{min-width:0;padding:.42rem .5rem}.vx-cost-metric+.vx-cost-metric{border-top:1px solid #e5e7eb}.vx-metric-label{font-size:.58rem;line-height:.75rem;font-weight:700;color:#64748b;white-space:nowrap}.vx-metric-value{margin-top:2px;font-size:.8rem;line-height:1rem;font-weight:850;color:#111827;white-space:nowrap;font-variant-numeric:tabular-nums}.dark .vx-cost-metrics{background:#182235;border-color:#334155}.dark .vx-cost-metric+.vx-cost-metric{border-color:#334155}.dark .vx-metric-value{color:#f8fafc}.vx-figure{font-size:.8rem;font-weight:800;color:rgb(31 41 55)}.dark .vx-figure{color:rgb(229 231 235)}.vx-card-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;padding:.65rem .8rem .8rem;border-top:1px solid rgb(243 244 246)}.dark .vx-card-actions{border-color:rgb(55 65 81)}.vx-card-action{min-height:38px;border:1px solid rgb(226 232 240);border-radius:.65rem;background:rgb(248 250 252);color:rgb(51 65 85);display:flex;align-items:center;justify-content:center;gap:.3rem;font-size:.68rem;font-weight:800;text-decoration:none;transition:.15s}.vx-card-action:hover{border-color:#7c3aed;color:#6d28d9;background:#f5f3ff}.vx-card-action svg{width:15px;height:15px;flex:none}.dark .vx-card-action{background:#182235;border-color:#334155;color:#e2e8f0}.dark .vx-card-action:hover{background:#24183f;border-color:#7c3aed;color:#ddd6fe}.vx-card-action.add-stock{color:#047857;background:#ecfdf5;border-color:#a7f3d0}.dark .vx-card-action.add-stock{color:#6ee7b7;background:#052e2b;border-color:#065f46}.vx-card-action.delete{color:#b91c1c;background:#fef2f2;border-color:#fecaca}.dark .vx-card-action.delete{color:#fca5a5;background:#3b0d0d;border-color:#7f1d1d}.vx-card-action.scan{color:#0369a1;background:#f0f9ff;border-color:#bae6fd}.dark .vx-card-action.scan{color:#7dd3fc;background:#082f49;border-color:#075985}.vx-empty{border:1px dashed rgb(209 213 219);border-radius:1rem;padding:3rem 1rem;text-align:center;color:rgb(107 114 128)}.vx-catalog-footer{display:flex;flex-direction:column;align-items:center;gap:.65rem;padding:.35rem 0 .25rem}.vx-load-more{display:inline-flex;min-height:46px;align-items:center;justify-content:center;border-radius:.75rem;border:1px solid rgb(209 213 219);background:white;padding:.65rem 1.1rem;font-size:.8rem;font-weight:800;color:rgb(55 65 81);box-shadow:0 1px 2px rgba(15,23,42,.04)}.vx-load-more:hover{border-color:#7c3aed;color:#7c3aed}.dark .vx-load-more{background:rgb(17 24 39);border-color:rgb(75 85 99);color:rgb(229 231 235)}.dark .vx-stock-in{background:#052e2b;color:#6ee7b7}.dark .vx-stock-low{background:#3b1d08;color:#fdba74}.dark .vx-stock-out{background:#3b0d0d;color:#fca5a5}.dark .vx-type{background:#1f2937;color:#d1d5db}.dark .vx-qty-metric{background:#052e2b;color:#6ee7b7}.dark .vx-qty-metric.low{background:#3b1d08;color:#fdba74}.dark .vx-qty-metric.out{background:#1f2937;color:#9ca3af}.dark .vx-metric-label{color:#94a3b8}
@media(min-width:720px){.vx-catalog-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.vx-product-body{padding:1rem}.vx-product-title{font-size:.95rem}.vx-card-actions{padding:.7rem 1rem 1rem}}@media(min-width:1100px){.vx-catalog-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(min-width:1380px){.vx-catalog-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:640px){body:has(.vx-inventory-list) .fi-page-header{display:none!important}.vx-inventory-desktop-tools{display:none!important}.vx-inventory-items-panel{border:0!important;background:transparent!important;box-shadow:none!important}.vx-inventory-items-panel>div{padding:0!important}.vx-health-grid{grid-template-columns:repeat(3,1fr);gap:8px}.vx-health:first-child{display:none}.vx-health{padding:10px 8px;border-radius:12px}.vx-health strong{font-size:1.15rem}.vx-health-label{font-size:.65rem}.vx-health-percent{font-size:.65rem}.vx-health-meta{align-items:flex-end;gap:4px}.vx-inv-toolbar{align-items:stretch}.vx-inv-search{min-width:100%}.vx-view-toggle{width:100%}.vx-view-btn{flex:1}.vx-load-more{width:100%;min-height:48px}.vx-card-actions{grid-template-columns:repeat(2,minmax(0,1fr))}.vx-card-action{min-height:42px;font-size:.72rem}body:has(.vx-inventory-list) .fi-modal-window{border-radius:24px 24px 0 0!important;box-shadow:0 -20px 60px rgba(15,23,42,.25)!important}.dark .vx-inventory-mobile-intro{color:#fff}body:has(.vx-inventory-list) .fi-ta{background:transparent!important}body:has(.vx-inventory-list) .fi-ta-ctn{border:0!important;background:transparent!important;box-shadow:none!important}body:has(.vx-inventory-list) .fi-ta-header-toolbar{padding:10px 0!important;gap:10px!important}body:has(.vx-inventory-list) .fi-ta-search-field{min-width:0!important;flex:1!important}body:has(.vx-inventory-list) .fi-ta-filters-trigger{border:1px solid #7c3aed!important;border-radius:10px!important;color:#c4b5fd!important}body:has(.vx-inventory-list) .fi-ta-content{background:transparent!important}body:has(.vx-inventory-list) .fi-ta-table{border-collapse:separate!important;border-spacing:0 12px!important}.dark body:has(.vx-inventory-list) .fi-ta-row{background:#101827!important;border-color:#263248!important}body:has(.vx-inventory-list) .fi-ta-row{display:grid!important;grid-template-columns:74px minmax(0,1fr) minmax(0,1fr)!important;background:#fff!important;border:1px solid #e5e7eb!important;border-radius:16px!important;padding:14px!important;overflow:hidden!important;box-shadow:0 8px 24px rgba(0,0,0,.16)!important}body:has(.vx-inventory-list) .fi-ta-cell{border:0!important;padding:5px 4px!important;min-width:0}body:has(.vx-inventory-list) .fi-ta-cell:first-child{grid-row:1/5;grid-column:1;width:70px!important}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(2){grid-column:2/4;font-size:1rem!important}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(3){grid-column:2/4}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(4){grid-column:1/2;margin-top:6px}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(5){grid-column:2/3;margin-top:6px}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(6){grid-column:3/4;margin-top:6px}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(7){grid-column:1/4;margin-top:2px}body:has(.vx-inventory-list) .fi-ta-actions-cell{grid-column:1/4!important;margin-top:8px!important}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(n+3):nth-child(-n+7)::before{display:block;margin-bottom:2px;font-size:.58rem;font-weight:700;line-height:1;text-transform:uppercase;letter-spacing:.06em;color:#7f8da3}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(3)::before{content:'SKU'}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(4)::before{content:'Type'}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(5)::before{content:'Status'}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(6)::before{content:'Qty on hand'}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(7)::before{content:'Added'}body:has(.vx-inventory-list) .fi-ta-image img{width:64px!important;height:64px!important;min-width:64px!important;border-radius:12px!important}body:has(.vx-inventory-list) .fi-ta-header-cell{display:none!important}}
@media(min-width:1024px){.vx-desktop-hidden{display:none}}@media(max-width:1023px){.vx-view-toggle,.vx-inventory-items-panel{display:none!important}.vx-catalog-view{display:block!important}.vx-metric-value{white-space:normal;overflow-wrap:anywhere}.vx-cost-metrics{overflow:visible}.vx-inv-search{min-width:0;width:100%}.vx-inv-toolbar select{max-width:100%;width:100%}}@media(max-width:359px){.vx-catalog-grid{grid-template-columns:minmax(0,1fr)}}
.vx-product-card{position:relative}.vx-select-check{position:absolute;top:8px;left:8px;z-index:2;display:grid;place-items:center;width:36px;height:36px;border-radius:10px;background:rgb(255 255 255/.92);box-shadow:0 1px 3px rgb(0 0 0/.15);cursor:pointer}.dark .vx-select-check{background:rgb(17 24 39/.9)}.vx-select-check input{width:18px;height:18px;border-radius:5px;accent-color:var(--primary-600);cursor:pointer}.vx-product-card.is-selected{border-color:var(--primary-500);box-shadow:0 0 0 2px var(--primary-500)}
.vx-bulk-bar{position:sticky;bottom:12px;z-index:20;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin-top:12px;padding:10px 12px;border:1px solid var(--vxw-border);border-radius:14px;background:var(--vxw-surface);box-shadow:var(--vxw-shadow-pop)}@media(max-width:1023px){.vx-bulk-bar{bottom:calc(68px + env(safe-area-inset-bottom,0px))}}
.vx-select-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}
/* Compact pass: one heading, a row of shortcuts, short stat tiles and small cards, so more fit and the page scrolls less. */
.fi-page-content:has(.vx-inventory-list){row-gap:1rem}
.vx-inv-shortcuts{display:flex;flex-wrap:wrap;gap:8px}
.vx-inv-shortcuts>a{display:inline-flex!important;align-items:center;gap:.4rem;padding:.45rem .8rem!important;border-radius:.65rem!important;font-size:.8rem!important;line-height:1.1rem}
.vx-inv-shortcuts>a svg{width:1rem;height:1rem;margin:0!important;flex:none}
.vx-inventory-list.space-y-3>*+*,.vx-inventory-list.sm\:space-y-5>*+*{margin-top:.75rem!important}
.vx-health{min-height:0;padding:9px 14px;border-radius:12px}.vx-health strong{font-size:1.3rem;line-height:1.6rem}.vx-health-meta{margin-top:0}
.vx-inventory-list>section.rounded-2xl{padding:.6rem .75rem!important}
.vx-inv-input{min-height:38px;padding:.45rem .75rem}
.vx-product-card{border-radius:.85rem}
.vx-product-image{aspect-ratio:16/9}
.vx-product-image img{padding:.4rem}
/* A real photo fills the whole frame; only the logo placeholder sits centred. */
.vx-product-card .vx-product-image.has-photo img{object-fit:cover!important;padding:0!important;width:100%!important;height:100%!important}
.vx-product-card .vx-product-body{padding:.6rem .7rem;gap:.35rem}
.vx-product-card .vx-product-title{font-size:.84rem;line-height:1.1rem}
.vx-product-tags{justify-content:flex-start;gap:.3rem;flex-wrap:nowrap;min-width:0}.vx-product-tags .vx-product-meta{margin-left:auto}
.vx-product-tags .vx-stock{padding:.15rem .45rem;font-size:.64rem;white-space:nowrap}
.vx-product-where{margin-top:-.1rem}
.vx-card-figs{display:grid;grid-template-columns:auto minmax(0,1fr) minmax(0,1.15fr);border:1px solid #eef2f7;border-radius:.6rem;background:#f8fafc;overflow:hidden;margin-top:auto}
.vx-card-fig{min-width:0;padding:.28rem .4rem}.vx-card-fig+.vx-card-fig{border-left:1px solid #e5e7eb}
.vx-card-fig .vx-metric-value{font-size:.72rem;letter-spacing:-.01em;margin-top:0;overflow:hidden;text-overflow:ellipsis}
.vx-card-fig.is-qty{background:#ecfdf5}.vx-card-fig.is-qty .vx-metric-value{color:#047857}.vx-card-fig.is-qty.low{background:#fff7ed}.vx-card-fig.is-qty.low .vx-metric-value{color:#c2410c}.vx-card-fig.is-qty.out .vx-metric-value{color:#64748b}
.dark .vx-card-figs{background:#182235;border-color:#334155}.dark .vx-card-fig+.vx-card-fig{border-color:#334155}.dark .vx-card-fig.is-qty{background:#052e2b}.dark .vx-card-fig.is-qty .vx-metric-value{color:#6ee7b7}.dark .vx-card-fig.is-qty.low{background:#3b1d08}.dark .vx-card-fig.is-qty.low .vx-metric-value{color:#fdba74}
/* Actions: icon over its name, four across, so every word shows and the card stays small. */
.vx-product-card .vx-card-actions{display:grid!important;grid-template-columns:repeat(4,minmax(0,1fr));gap:4px;padding:.45rem .5rem .55rem!important}
.vx-product-card .vx-card-action{flex-direction:column;gap:1px;min-width:0;min-height:38px!important;padding:3px 2px!important;grid-column:auto!important;border-radius:.5rem;font-size:.6rem!important;line-height:.75rem;letter-spacing:-.01em}
.vx-product-card .vx-card-action svg{width:14px;height:14px}
.vx-product-card .vx-card-action span{display:block;max-width:100%;overflow:visible!important;text-overflow:clip!important;white-space:normal!important;text-align:center;overflow-wrap:normal;word-break:keep-all}
.vx-select-check{width:30px;height:30px;top:6px;left:6px;border-radius:8px}.vx-select-check input{width:16px;height:16px}
@media(max-width:639px){.vx-inventory-list .vx-catalog-view .vx-catalog-grid{grid-template-columns:minmax(0,1fr)!important}.vx-product-card .vx-product-image{aspect-ratio:16/9;height:auto}.vx-product-card .vx-product-title{display:-webkit-box;-webkit-line-clamp:2;font-size:.84rem;line-height:1.1rem;overflow:hidden}.vx-product-card .vx-product-meta{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vx-product-tags{flex-wrap:nowrap}.vx-product-tags .vx-product-meta{display:none}.vx-product-card .vx-card-action{min-height:40px!important}.vx-card-figs{grid-template-columns:1fr 1fr}.vx-card-fig:last-child{grid-column:1/-1;border-left:0;border-top:1px solid #e5e7eb}}
@media(max-width:359px){.vx-inventory-list .vx-catalog-view .vx-catalog-grid{grid-template-columns:minmax(0,1fr)!important}}

/* Align all stock/type badges consistently, including multi-word labels. */
.vx-product-tags{flex-wrap:wrap;align-items:center;justify-content:flex-start;row-gap:5px}
.vx-product-tags .vx-stock,.vx-product-tags .vx-type{display:inline-flex;align-items:center;justify-content:center;vertical-align:middle;text-align:center;min-height:22px;line-height:1.1;padding:4px 8px;white-space:normal}
.vx-product-tags .vx-product-meta{margin-left:0}
.vx-health[wire\\:loading]{cursor:progress}

/* Compact Operations Inventory layout — visual only; Livewire actions and stock queries unchanged. */
.vx-inventory-list{gap:.7rem!important}
.vx-inventory-summary{display:grid;grid-template-columns:minmax(170px,1fr) minmax(205px,260px) minmax(420px,1.8fr);align-items:center;gap:12px;border:1px solid #ddd6fe;border-radius:18px;padding:12px 14px;background:linear-gradient(115deg,#faf5ff,#fff 65%);box-shadow:0 2px 10px rgba(76,29,149,.05)}
.vx-inventory-heading h1{font-size:1.5rem;line-height:1.2;font-weight:850;color:#1e1b4b}.vx-inventory-heading p{font-size:.75rem;color:#64748b;margin-top:3px}
.vx-inventory-summary section[aria-label="Inventory warehouse selector"]{justify-content:flex-start;min-width:0}.vx-inventory-summary section[aria-label="Inventory warehouse selector"]>div{width:100%;min-width:0}.vx-inventory-summary #vx-warehouse-location{min-width:0!important;width:100%;padding-block:.5rem}
.vx-inventory-summary .vx-health-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;min-width:0}
.vx-inventory-summary .vx-health{min-height:64px;padding:8px;border-radius:11px;box-shadow:none}.vx-inventory-summary .vx-health strong{font-size:1.2rem;line-height:1.25}.vx-inventory-summary .vx-health-meta{display:block;margin-top:2px}.vx-inventory-summary .vx-health-label{font-size:.67rem}.vx-inventory-summary .vx-health-percent{display:none}
.vx-inventory-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px;border:1px solid #e9d5ff;border-radius:15px;padding:8px;background:#fff}
.vx-inventory-actions .vx-inventory-desktop-tools{flex:1;min-width:0}.vx-inventory-actions .vx-inv-shortcuts{display:flex;flex-wrap:wrap;gap:7px;align-items:center}
.vx-inventory-actions .vx-inv-shortcuts>a{display:inline-flex;align-items:center;gap:6px;min-height:37px;padding:7px 10px;border-radius:10px;font-size:.75rem;white-space:nowrap}
.vx-inventory-actions .vx-inv-shortcuts>a svg{width:16px;height:16px;margin:0}
.vx-inventory-actions>div.flex{margin:0}
.vx-inventory-actions>div.flex a{padding:7px 10px;font-size:.75rem}
.vx-inventory-list>section.rounded-2xl{padding:10px!important;border-color:#e9d5ff}
.vx-inventory-list .vx-inv-toolbar{gap:8px}
.vx-inventory-list .vx-inv-input{min-height:39px}
.vx-inventory-list .vx-product-card{border-color:#ddd6fe;box-shadow:0 2px 8px rgba(76,29,149,.05)}
.vx-inventory-list .vx-product-card:hover{border-color:#8b5cf6;box-shadow:0 8px 20px rgba(76,29,149,.12)}
.vx-inventory-list .vx-product-image{aspect-ratio:5/4}
.vx-inventory-list .vx-product-image img{object-fit:contain}
.vx-inventory-list .vx-card-actions{grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;padding:8px}
.vx-inventory-list .vx-card-action{min-width:0;min-height:35px;font-size:.68rem}
.vx-inventory-list .vx-card-action:focus-visible,.vx-inventory-list .vx-health:focus-visible{outline:2px solid #7c3aed;outline-offset:2px}
.dark .vx-inventory-summary{background:linear-gradient(115deg,#201333,#101827 75%);border-color:#5b21b6}.dark .vx-inventory-heading h1{color:#f5f3ff}.dark .vx-inventory-heading p{color:#c4b5fd}.dark .vx-inventory-actions{background:#111827;border-color:#4c1d95}.dark .vx-inventory-list>section.rounded-2xl{border-color:#4c1d95}
@media(min-width:1650px){.vx-inventory-list .vx-catalog-grid{grid-template-columns:repeat(5,minmax(0,1fr))}}
@media(min-width:1950px){.vx-inventory-list .vx-catalog-grid{grid-template-columns:repeat(6,minmax(0,1fr))}}
@media(max-width:1279px){.vx-inventory-summary{grid-template-columns:minmax(0,1fr) minmax(200px,1fr)}.vx-inventory-summary .vx-health-grid{grid-column:1/-1}}
@media(max-width:640px){.vx-inventory-mobile-intro{display:none!important}.vx-inventory-summary{grid-template-columns:minmax(0,1fr);padding:10px}.vx-inventory-summary .vx-health-grid{grid-column:auto;grid-template-columns:repeat(4,minmax(0,1fr))}.vx-inventory-summary .vx-health:first-child{display:block}.vx-inventory-summary .vx-health{padding:7px 4px;min-height:60px}.vx-inventory-summary .vx-health strong{font-size:1rem}.vx-inventory-summary .vx-health-label{font-size:.57rem}.vx-inventory-actions .vx-inventory-desktop-tools{display:block!important}.vx-inventory-actions{align-items:stretch}.vx-inventory-actions .vx-inv-shortcuts>a{min-height:42px}.vx-inventory-list .vx-card-actions{grid-template-columns:repeat(2,minmax(0,1fr))}.vx-inventory-list .vx-card-action{min-height:42px}.vx-inventory-list .vx-catalog-grid{grid-template-columns:repeat(1,minmax(0,1fr))}}
@media(min-width:480px) and (max-width:719px){.vx-inventory-list .vx-catalog-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(prefers-reduced-motion:reduce){.vx-inventory-list .vx-product-card{transition:none}.vx-inventory-list .vx-product-card:hover{transform:none}}

.vx-inventory-list .vx-catalog-footer{display:flex;flex-direction:row;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:center;padding:10px 2px}.vx-catalog-pagination{display:flex;align-items:center;gap:5px;flex-wrap:wrap}.vx-catalog-pagination button{min-width:34px;min-height:36px;padding:4px 8px;border:1px solid #ddd6fe;border-radius:9px;background:white;color:#5b21b6;font-weight:700}.vx-catalog-pagination button[aria-current=page]{background:#7c3aed;color:white;border-color:#7c3aed}.vx-catalog-pagination button:disabled{opacity:.4;cursor:not-allowed}.vx-catalog-pagination button:focus-visible{outline:2px solid #7c3aed;outline-offset:2px}.dark .vx-catalog-pagination button{background:#211632;border-color:#6d28d9;color:#e9d5ff}.dark .vx-catalog-pagination button[aria-current=page]{background:#7c3aed;color:white}

/* Override legacy card CSS without altering any of the original action bindings. */
.vx-inventory-list .vx-product-card .vx-card-actions{grid-template-columns:repeat(3,minmax(0,1fr))!important;padding:8px!important;gap:5px!important}
.vx-inventory-list .vx-product-card .vx-card-action{flex-direction:row;min-height:38px!important;font-size:.67rem!important;padding:5px 3px!important;gap:3px}
.vx-inventory-list .vx-product-card .vx-card-action span{overflow-wrap:anywhere!important;word-break:normal}
.vx-inventory-list .vx-product-card .vx-product-image.has-photo img{object-fit:contain!important;padding:.3rem!important}
@media(max-width:1023px){.vx-inventory-list .vx-view-toggle{display:inline-flex!important}.vx-inventory-list .vx-inventory-items-panel{display:block!important;max-width:100%;overflow-x:auto}.vx-inventory-list .vx-catalog-view.vx-desktop-hidden{display:none!important}}
@media(max-width:640px){.vx-inventory-list .vx-product-card .vx-card-actions{grid-template-columns:repeat(2,minmax(0,1fr))!important}.vx-inventory-list .vx-product-card .vx-card-action{min-height:44px!important}.vx-inventory-list .vx-catalog-view.vx-desktop-hidden{display:none!important}}

/* Inventory screenshot follow-up: keep the original top menu, remove the empty
   Filament header wrapper, and use one truly compact desktop control area. */
body:has(.vx-inventory-list) .fi-page-header{display:none!important}
body:has(.vx-inventory-list) .fi-page-content{gap:.65rem!important}
.vx-inventory-list{gap:.6rem!important}
.vx-inventory-summary{grid-template-columns:minmax(180px,.8fr) minmax(190px,.8fr) minmax(0,2.3fr)!important;gap:10px!important;padding:10px 12px!important}
.vx-inventory-summary .vx-health-grid{grid-column:auto!important;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:6px!important}
.vx-inventory-summary .vx-health{min-height:62px!important;padding:8px!important}
.vx-inventory-summary .vx-health strong{font-size:1.18rem!important}
.vx-inventory-summary .vx-health-meta{margin-top:1px!important}
.vx-inventory-actions{display:flex!important;flex-wrap:wrap!important;gap:6px!important;padding:8px!important;align-items:center!important}
.vx-inventory-actions .vx-inventory-desktop-tools{flex:0 1 auto!important;min-width:0!important}
.vx-inventory-actions .vx-inv-shortcuts{display:flex!important;flex-wrap:wrap!important;gap:6px!important}
.vx-inventory-actions .vx-inv-shortcuts>a{min-height:36px!important;padding:6px 9px!important}
.vx-inventory-actions>div.flex{gap:6px!important}
.vx-inventory-list>section.rounded-2xl{padding:9px!important}
.vx-inventory-list .vx-inv-toolbar{gap:8px!important}
.vx-inventory-list .vx-inv-input{min-height:40px!important}
@media(min-width:1450px){.vx-inventory-actions{flex-wrap:nowrap!important}.vx-inventory-actions .vx-inventory-desktop-tools{flex:1 1 auto!important}}
@media(max-width:1279px){.vx-inventory-summary{grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important}.vx-inventory-summary .vx-health-grid{grid-column:1/-1!important}}
@media(max-width:640px){.vx-inventory-summary{grid-template-columns:minmax(0,1fr)!important}.vx-inventory-summary .vx-health-grid{grid-column:auto!important;grid-template-columns:repeat(2,minmax(0,1fr))!important}.vx-inventory-actions .vx-inventory-desktop-tools{flex:1 1 100%!important}}

/* Follow-up to live inventory screenshots: reduce card height without hiding actions. */
.vx-inventory-list .vx-product-image{aspect-ratio:auto!important;height:132px!important;min-height:0!important}
.vx-inventory-list .vx-product-image:not(.has-photo) img{width:auto!important;height:auto!important;max-width:98px!important;max-height:98px!important;padding:6px!important;object-fit:contain!important}
.vx-inventory-list .vx-product-image.has-photo img{width:100%!important;height:100%!important;object-fit:contain!important;padding:5px!important}
.vx-inventory-list .vx-product-body{padding:9px 10px!important;gap:5px!important}
.vx-inventory-list .vx-product-title{font-size:.84rem!important;line-height:1.2rem!important}
.vx-inventory-list .vx-card-actions{grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:5px!important;padding:7px!important}
.vx-inventory-list .vx-card-action{min-width:0!important;min-height:40px!important;padding:5px 3px!important;gap:4px!important;font-size:.69rem!important;line-height:1.1!important;white-space:normal!important}
.vx-inventory-list .vx-card-action span{overflow-wrap:normal!important;word-break:normal!important}
.vx-inventory-list .vx-inventory-actions{margin:0!important}
.vx-inventory-list .vx-inventory-summary{margin:0!important}
.vx-inventory-list .vx-catalog-view{margin-top:0!important}
body:has(.vx-inventory-list) .fi-page-content{gap:.45rem!important}
body:has(.vx-inventory-list) .fi-header{margin-bottom:0!important;padding-bottom:0!important}
@media(min-width:1280px){.vx-inventory-list .vx-inventory-summary{grid-template-columns:minmax(155px,.65fr) minmax(220px,.8fr) minmax(0,2.4fr)!important}.vx-inventory-list .vx-inventory-actions{padding:7px!important}}
@media(max-width:640px){.vx-inventory-list .vx-product-image{height:115px!important}.vx-inventory-list .vx-product-image:not(.has-photo) img{max-height:78px!important;max-width:78px!important}.vx-inventory-list .vx-card-actions{grid-template-columns:repeat(2,minmax(0,1fr))!important}.vx-inventory-list .vx-card-action{min-height:44px!important;font-size:.7rem!important}}
</style>
@if(auth()->user()?->isFulfillment() || auth()->user()?->isFulfillmentAdmin())
    @php
        $directoryItems = $this->fulfillmentDirectoryItems;
        $directoryTotal = $this->fulfillmentDirectoryTotal;
        $directoryPages = max(1, (int) ceil($directoryTotal / max(1, $catalogVisible)));
        $directoryCurrent = max(1, min($catalogPage, $directoryPages));
        $directoryPlaceholder = \App\Models\Product::placeholderImageUrl();
    @endphp
    <section class="space-y-4 vx-inventory-list" aria-label="Fulfillment inventory catalog">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-gray-950 dark:text-white">All Inventory</h1>
                <p class="text-sm text-gray-500">Product pictures, names and costs</p>
            </div>
            <span class="text-sm text-gray-500">{{ number_format($directoryTotal) }} products</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <input wire:model.live.debounce.500ms="catalogSearch" type="search" class="vx-inv-input flex-1 min-w-[220px]"
                placeholder="Search products, SKU or barcode" aria-label="Search inventory products">
            <select wire:model.live="catalogSort" class="vx-inv-input max-w-[190px]" aria-label="Sort inventory">
                <option value="name">Name A–Z</option>
                <option value="newest">Newest first</option>
                <option value="value">Highest cost</option>
            </select>
        </div>
        @if($directoryItems->isNotEmpty())
            <div class="vx-catalog-grid">
                @foreach($directoryItems as $item)
                    <article class="vx-product-card" wire:key="fulfillment-product-{{ $item->id }}">
                        <div @class(['vx-product-image', 'has-photo' => $item->hasImage()])>
                            <img loading="lazy" decoding="async" src="{{ $item->hasImage() ? $item->imageUrl() : $directoryPlaceholder }}" alt="{{ $item->name }}">
                        </div>
                        <div class="vx-product-body">
                            <div class="vx-product-title">{{ $item->name }}</div>
                            <div class="vx-product-meta">Item cost: <strong>${{ number_format((float) $item->unit_cost, 2) }}</strong></div>
                        </div>
                    </article>
                @endforeach
            </div>
            <nav class="flex flex-wrap items-center justify-between gap-3" aria-label="Inventory pages">
                <span class="text-sm text-gray-500">Showing {{ number_format(($directoryCurrent - 1) * $catalogVisible + 1) }}–{{ number_format(min($directoryCurrent * $catalogVisible, $directoryTotal)) }} of {{ number_format($directoryTotal) }}</span>
                <div class="flex items-center gap-2">
                    <button class="vxw-btn vxw-btn--sm" type="button" wire:click="setCatalogPage({{ $directoryCurrent - 1 }})" @disabled($directoryCurrent <= 1)>Previous</button>
                    <span class="text-sm">Page {{ $directoryCurrent }} of {{ $directoryPages }}</span>
                    <button class="vxw-btn vxw-btn--sm" type="button" wire:click="setCatalogPage({{ $directoryCurrent + 1 }})" @disabled($directoryCurrent >= $directoryPages)>Next</button>
                </div>
            </nav>
        @else
            <p class="rounded-xl border p-5 text-sm text-gray-500">No matching products.</p>
        @endif
    </section>
@else
<div x-data x-on:barcode-scanned.window="if ($wire.quickStockScanTargetId) { $wire.verifyQuickStockBarcode($event.detail.value) } else if ($wire.barcodeScanTargetId) { $wire.saveScannedBarcode($event.detail.value) }"></div>
<div class="space-y-3 sm:space-y-5 vx-inventory-list" data-vx-page="inventory-center">
 <div class="vx-inventory-mobile-intro sm:hidden"><h2 class="text-2xl font-bold text-gray-950 dark:text-white">All Inventory</h2><p class="mt-1 text-sm text-gray-500">Find products, check stock and manage inventory.</p></div>
 <header class="vx-inventory-summary">
    <div class="vx-inventory-heading"><h1>All Inventory</h1><p>Browse and manage products across permitted locations.</p></div>
    <section class="flex flex-wrap items-center justify-end gap-3" aria-label="Inventory warehouse selector">
  <div class="sr-only">Warehouse inventory: product counts, stock totals and cards reflect the selected location.</div>
  <div class="flex w-full items-center gap-2 sm:w-auto">
   <label for="vx-warehouse-location" class="shrink-0 text-sm font-semibold text-gray-700 dark:text-gray-200">Location</label>
   <select id="vx-warehouse-location" wire:model.live="inventoryLocation" class="min-w-0 flex-1 rounded-xl border border-gray-300 bg-white px-3 py-2.5 text-sm font-semibold text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-white sm:min-w-56" aria-label="Choose warehouse for inventory totals">
    @if($inventoryLocation === '')
     <option value="" selected hidden>{{ $this->inventoryLocationOptions()[$this->inventoryPageLocationId()] ?? 'Main Warehouse' }}</option>
    @endif
    @if((auth()->user()?->isAdmin() || auth()->user()?->isOwner()) && ! \App\Support\InventoryVisibility::isReadOnlyStreamer(auth()->user()))<option value="all">All Locations — combined totals</option>@endif
    @foreach($this->inventoryLocationOptions() as $locationId => $locationName)
     <option value="{{ $locationId }}">{{ $locationName }}</option>
    @endforeach
   </select>
  </div>
 </section>
    <div class="vx-health-grid">
  @foreach($this->getStats() as $stat)
   @if($stat['key'])<button type="button" wire:click="filterStock('{{ $stat['key'] }}')" x-on:click="pendingStock = '{{ $stat['key'] }}'" x-bind:class="{ 'active': pendingStock === '{{ $stat['key'] }}' }" class="vx-health {{ $stockHealth === $stat['key'] ? 'active' : '' }}"><strong>{{ $stat['value'] }}</strong><div class="vx-health-meta"><span class="vx-health-label">{{ $stat['label'] }}</span><span class="vx-health-percent">{{ $stat['percentage'] }}</span></div></button>
   @else<button type="button" wire:click="filterStock(null)" x-on:click="pendingStock = 'all'" x-bind:class="{ 'active': pendingStock === 'all' }" class="vx-health {{ $stockHealth === $stat['key'] ? 'active' : '' }}"><strong>{{ $stat['value'] }}</strong><div class="vx-health-meta"><span class="vx-health-label">{{ $stat['label'] }}</span><span class="vx-health-percent">{{ $stat['percentage'] }}</span></div></button>@endif
  @endforeach
 </div>
 </header>
 <div class="vx-inventory-actions">
  <livewire:inventory-recent-changes wire:key="inventory-list-recent-changes" /><section class="vx-inventory-desktop-tools">
  {{-- The page header already names the page; this row is just the shortcuts. --}}
  <div>
   <div class="vx-inv-shortcuts">
    @if(\App\Filament\Pages\InventoryScanner::canAccess())
    <a href="{{ \App\Filament\Pages\InventoryScanner::getUrl() }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-primary-600 shadow-sm dark:border-gray-700 dark:bg-gray-900"><x-heroicon-o-qr-code class="mb-1 h-5 w-5"/>Quick Scan</a>
    @endif
    @if(\App\Support\StockGate::canChangeAny())
    <a href="{{ \App\Filament\Pages\ImportInventorySheet::getUrl() }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-blue-600 shadow-sm dark:border-gray-700 dark:bg-gray-900"><x-heroicon-o-arrow-up-tray class="mb-1 h-5 w-5"/>Import Sheet</a>
    <a href="{{ \App\Filament\Resources\PalletResource::getUrl('index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-emerald-600 shadow-sm dark:border-gray-700 dark:bg-gray-900"><x-heroicon-o-inbox-arrow-down class="mb-1 h-5 w-5"/>Receive</a>
    <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('quick-add') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-bold text-violet-600 shadow-sm dark:border-gray-700 dark:bg-gray-900"><x-heroicon-o-bolt class="mb-1 h-5 w-5"/>Quick Add</a>
    @endif
    <a href="{{ \App\Filament\Pages\InventoryReport::getUrl() }}" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm font-bold text-primary-700 shadow-sm dark:border-primary-800 dark:bg-primary-950/30 dark:text-primary-300"><x-heroicon-o-chart-bar class="mb-1 h-5 w-5"/>View Report</a>
    <a href="{{ route('export.inventory-pdf') }}?download=1" target="_blank" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm font-bold text-primary-700 shadow-sm dark:border-primary-800 dark:bg-primary-950/30 dark:text-primary-300"><x-heroicon-o-document-arrow-down class="mb-1 h-5 w-5"/>Report PDF</a>
    <a href="{{ route('export.inventory-items') }}" target="_blank" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm font-bold text-primary-700 shadow-sm dark:border-primary-800 dark:bg-primary-950/30 dark:text-primary-300"><x-heroicon-o-table-cells class="mb-1 h-5 w-5"/>Export Excel</a>
   </div>
  </div>
 </section>
 @if(auth()->user()?->isAdmin())
 <div class="flex flex-wrap items-center gap-2">
  <a href="{{ route('export.inventory-prices') }}" class="inline-flex items-center gap-2 rounded-xl border border-primary-200 bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700 dark:border-primary-800 dark:bg-primary-950/30 dark:text-primary-300"><x-heroicon-o-arrow-down-tray class="h-4 w-4"/>Inventory & Prices (.xlsx)</a>
  @if(\App\Services\InventoryCountReset::canReset(auth()->user()))<x-filament::button color="danger" size="sm" outlined icon="heroicon-o-arrow-path" wire:click="mountAction('resetAllCounts')">Reset all stock</x-filament::button>@endif
 </div>
 @endif</div>
 <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
  @php $fulfillmentCatalogOnly = (bool) (auth()->user()?->isFulfillment() || auth()->user()?->isFulfillmentAdmin()); @endphp
<div class="vx-inv-toolbar">
    <div class="vx-inv-search {{ ! $fulfillmentCatalogOnly && $viewMode === 'table' ? 'vx-desktop-hidden' : '' }}"><input wire:model.live.debounce.500ms="catalogSearch" class="vx-inv-input" type="search" placeholder="Search products, SKU, UPC, barcode, brand…" /></div>
   @if(! $fulfillmentCatalogOnly && $viewMode === 'table')<div class="hidden lg:block">Detailed inventory table</div>@endif
   @unless($fulfillmentCatalogOnly)<div class="vx-view-toggle" aria-label="Inventory view">
    <button type="button" wire:click="setViewMode('catalog')" class="vx-view-btn {{ $viewMode === 'catalog' ? 'active' : '' }}"><x-heroicon-o-squares-2x2 class="h-4 w-4" /> Cards</button>
    <button type="button" wire:click="setViewMode('table')" class="vx-view-btn {{ $viewMode === 'table' ? 'active' : '' }}"><x-heroicon-o-list-bullet class="h-4 w-4" /> Table</button>
   </div>@endunless
    <select wire:model.live="catalogSort" class="{{ ! $fulfillmentCatalogOnly && $viewMode === 'table' ? 'vx-desktop-hidden' : '' }} min-h-11 rounded-lg border-gray-200 bg-white text-sm dark:border-gray-700 dark:bg-gray-800"><option value="name">Sort: Name A-Z</option><option value="qty">Quantity: High-Low</option><option value="newest">Newest</option></select>
   @if(filled($catalogSearch))<button wire:click="clearCatalogSearch" class="{{ ! $fulfillmentCatalogOnly && $viewMode === 'table' ? 'vx-desktop-hidden' : '' }} min-h-11 rounded-lg px-3 text-sm font-semibold text-primary-600">Clear</button>@endif
  </div>
  @if($stockHealth)<div class="mt-3 flex items-center justify-between rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs dark:border-primary-900 dark:bg-primary-950/20"><span>Showing <strong>{{ match($stockHealth) { 'in' => 'In Stock', 'low' => 'Low Stock', 'out' => 'Out of Stock' } }}</strong></span><button wire:click="filterStock(null)" class="font-bold text-primary-600">Show all</button></div>@endif
 </section>

 <div class="vx-catalog-view {{ ! $fulfillmentCatalogOnly && $viewMode === 'table' ? 'vx-desktop-hidden' : '' }}">
  @php $canBulkDelete = \App\Filament\Resources\InventoryItemResource::canDeleteAny(); $canResetStock = \App\Services\InventoryCountReset::canReset(auth()->user()); $canSelectStock = $canBulkDelete || $canResetStock; @endphp
  @if($this->catalogItems->isNotEmpty())
   @if($canSelectStock)
    @php $filterLabel = match($stockHealth) { 'in' => 'in-stock', 'low' => 'low-stock', 'out' => 'out-of-stock', default => '' }; @endphp
    <div class="vx-select-row">
     <span class="vxw-meta">
      @if($selectAllMatching)
       All {{ number_format($this->catalogTotal) }} matching{{ $filterLabel ? ' ' . $filterLabel : '' }} items selected{{ filled($catalogSearch) ? ' for “' . $catalogSearch . '”' : '' }}
      @else
       <span x-text="$wire.selectedItems.length ? $wire.selectedItems.length + ' selected' : 'Tick items, or select everything that matches the filter'"></span>
      @endif
     </span>
     <div class="flex gap-2">
      @unless($selectAllMatching)
       <button type="button" class="vxw-btn vxw-btn--sm" wire:click="selectAllMatchingItems" wire:loading.attr="disabled" wire:target="selectAllMatchingItems">Select all {{ number_format($this->catalogTotal) }}{{ $filterLabel ? ' ' . $filterLabel : '' }}</button>
      @endunless
      @if($selectAllMatching)
       <button type="button" class="vxw-btn vxw-btn--ghost vxw-btn--sm" wire:click="clearSelection">Clear</button>
      @else
       <button type="button" class="vxw-btn vxw-btn--ghost vxw-btn--sm" wire:click="clearSelection" x-show="$wire.selectedItems.length" x-cloak>Clear</button>
      @endif
     </div>
    </div>
   @endif
   @php
    // Resolve the fallback image once per render, not for every product.
    // Product::placeholderImageUrl() reads channel/branding settings and can
    // otherwise cause dozens of redundant queries on a stock-filter click.
    $catalogPlaceholderImage = \App\Models\Product::placeholderImageUrl();
    $catalogCanEdit = \App\Filament\Resources\InventoryItemResource::canEdit(new \App\Models\InventoryItem());
    $catalogCanStock = \App\Filament\Resources\InventoryItemResource::canManageStock();
    $catalogCanDelete = \App\Filament\Resources\InventoryItemResource::canDeleteAny();
   @endphp
   <div class="vx-catalog-grid">
    @foreach($this->catalogItems as $item)
     @php
      $onHand=(float)($item->stock_sum_quantity??0);$reorder=$item->reorder_level!==null?(float)$item->reorder_level:null;$stockState=$onHand<=0?'out':(($reorder!==null&&$onHand<=$reorder)?'low':'in');$locations=$item->stock->where('quantity','>',0)->pluck('location.name')->filter()->unique()->take(2)->implode(', ');$canEdit=$catalogCanEdit;$canStock=$catalogCanStock;$canDelete=$catalogCanDelete;$canScan=$canStock;
     @endphp
     <article @class(['vx-product-card', 'is-selected' => $selectAllMatching]) wire:key="inv-card-{{ $item->getKey() }}" @if($canSelectStock && ! $selectAllMatching) :class="$wire.selectedItems.includes('{{ $item->getKey() }}') && 'is-selected'" @endif>
      @if($canSelectStock && ! $selectAllMatching)<label class="vx-select-check" title="Select {{ $item->name }}"><input type="checkbox" wire:model="selectedItems" value="{{ $item->getKey() }}" aria-label="Select {{ $item->name }}"></label>@endif
      @unless($fulfillmentCatalogOnly)<a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view',['record'=>$item]) }}" class="vx-product-link">@endunless
       <div @class(['vx-product-image', 'has-photo' => $item->hasImage()])><img loading="lazy" decoding="async" src="{{ $item->hasImage() ? $item->imageUrl() : $catalogPlaceholderImage }}" alt="{{ $item->name }}" /></div>
      @unless($fulfillmentCatalogOnly)</a>@endunless
      <div class="vx-product-body">
       @if($fulfillmentCatalogOnly)<div class="vx-product-title">{{ $item->name }}</div>@else<a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view',['record'=>$item]) }}" class="vx-product-link"><div class="vx-product-title">{{ $item->name }}</div></a>@endif
       @if($fulfillmentCatalogOnly)
        <div class="vx-product-meta">Item Cost: <strong>${{ number_format((float)$item->unit_cost, 2) }}</strong></div>
       @else
       <div class="vx-product-meta">{{ $item->sku ?: 'No SKU' }}@if($item->brand) · {{ $item->brand }}@endif</div>
       <div class="vx-product-row vx-product-tags"><span class="vx-stock vx-stock-{{ $stockState }}">{{ $stockState==='out'?'Out of stock':($stockState==='low'?'Low stock':'In stock') }}</span><span class="vx-type">{{ $item->is_container ? 'Case' : 'Item' }}</span>@if($item->created_at && $item->created_at->gte(now()->subDays(7)))<span class="vx-type" title="Added within the last seven days">New</span>@endif@if($onHand > 0 && $item->last_receipt_at && \Illuminate\Support\Carbon::parse($item->last_receipt_at)->gte(now()->subDays(7)))<span class="vx-type" title="Inventory received within the last seven days">Recently received</span>@endif@if($item->category)<span class="vx-product-meta">{{ $item->category }}</span>@endif</div>
       <div class="vx-product-meta vx-product-where" title="{{ $locations ?: 'No stocked location' }}"><x-heroicon-m-map-pin class="inline h-3 w-3 align-[-2px]" /> {{ $locations ?: 'No stocked location' }}</div>
       @endif
       @unless($fulfillmentCatalogOnly)<div class="vx-card-figs">
        <div class="vx-card-fig is-qty {{ $stockState }}"><div class="vx-metric-label">Qty</div><div class="vx-metric-value">{{ number_format($onHand) }}</div></div>
        <div class="vx-card-fig"><div class="vx-metric-label">Item Cost</div><div class="vx-metric-value" title="Item cost ${{ number_format((float)$item->unit_cost,2) }}">${{ number_format((float)$item->unit_cost,2) }}</div></div>
        <div class="vx-card-fig"><div class="vx-metric-label">Value</div><div class="vx-metric-value" title="Total value ${{ number_format($onHand*(float)$item->costBasis(),2) }}">${{ number_format($onHand*(float)$item->costBasis(),2) }}</div></div>
       </div>@endunless
      </div>
      @unless($fulfillmentCatalogOnly || \App\Support\InventoryVisibility::isReadOnlyStreamer())
      <div class="vx-card-actions">
       <button type="button" wire:click="mountAction('itemHistory', { item: {{ $item->getKey() }} })" wire:loading.attr="disabled" class="vx-card-action" title="Item history"><x-heroicon-o-clock /> <span>History</span></button>
       @if($canStock)<button type="button" wire:click="mountAction('add_stock', { product: {{ $item->getKey() }} })" wire:loading.attr="disabled" wire:target="mountAction('add_stock')" class="vx-card-action add-stock" data-stock-product-id="{{ $item->getKey() }}" title="Add stock"><x-heroicon-o-plus-circle /> <span>Add stock</span></button>@endif
       <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view',['record'=>$item]) }}" class="vx-card-action" title="View item"><x-heroicon-o-eye /> <span>View</span></a>
       @if($canEdit)<a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('edit',['record'=>$item]) }}" class="vx-card-action" title="Edit item"><x-heroicon-o-pencil-square /> <span>Edit</span></a>@endif
       @if($canStock)<a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('stock',['record'=>$item]) }}" class="vx-card-action" title="Manage stock"><x-heroicon-o-arrows-right-left /> <span>Stock</span></a>@endif
       @if($canScan)<button type="button" wire:click.stop="startBarcodeScan({{ $item->getKey() }})" class="vx-card-action scan" title="{{ filled($item->barcode) ? 'Replace barcode' : 'Scan barcode' }}"><x-heroicon-o-qr-code /> <span>Scan</span></button>@endif
       @if($canDelete)<button type="button" wire:click="mountAction('deleteItem', { product: {{ $item->getKey() }} })" class="vx-card-action delete" title="Delete item"><x-heroicon-o-trash /> <span>Delete</span></button>@endif
      </div>
      @endunless
     </article>
    @endforeach
   </div>
   @if($canSelectStock)
    <div class="vx-bulk-bar" @unless($selectAllMatching) x-show="$wire.selectedItems.length" x-cloak @endunless x-transition.opacity.duration.150ms role="region" aria-label="Bulk actions">
     <span class="text-sm font-semibold">@if($selectAllMatching){{ number_format($this->catalogTotal) }} selected @else<span x-text="$wire.selectedItems.length"></span> selected @endif</span>
     <div class="flex gap-2">
      <button type="button" class="vxw-btn vxw-btn--ghost vxw-btn--sm" wire:click="clearSelection">Clear</button>
      @if($canResetStock)<button type="button" class="vxw-btn vxw-btn--sm" wire:click="mountAction('resetSelectedCounts')" wire:loading.attr="disabled" wire:target="mountAction"><x-heroicon-o-arrow-path class="h-4 w-4" /> Reset selected stock</button>@endif
      @if($canBulkDelete)<button type="button" class="vxw-btn vxw-btn--sm" style="background:#e11d48;border-color:transparent;color:#fff" wire:click="mountAction('bulkDeleteItems')" wire:loading.attr="disabled" wire:target="mountAction"><x-heroicon-o-trash class="h-4 w-4" /> Delete selected</button>@endif
     </div>
    </div>
   @endif
   @php
    $catalogPages = max(1, (int) ceil($this->catalogTotal / $catalogVisible));
    $catalogCurrent = max(1, min($catalogPage, $catalogPages));
    $catalogFirst = ($catalogCurrent - 1) * $catalogVisible + 1;
    $catalogLast = min($catalogCurrent * $catalogVisible, $this->catalogTotal);
   @endphp
   <nav class="vx-catalog-footer" aria-label="Inventory card pagination">
    <div class="text-xs text-gray-600 dark:text-gray-300">Showing {{ number_format($catalogFirst) }}–{{ number_format($catalogLast) }} of {{ number_format($this->catalogTotal) }} matching products</div>
    <div class="vx-catalog-pagination">
     <button type="button" wire:click="setCatalogPage(1)" @disabled($catalogCurrent <= 1) aria-label="First page">«</button>
     <button type="button" wire:click="setCatalogPage({{ $catalogCurrent - 1 }})" @disabled($catalogCurrent <= 1) aria-label="Previous page">‹</button>
     @for($page = max(1, $catalogCurrent - 2); $page <= min($catalogPages, $catalogCurrent + 2); $page++)
      <button type="button" wire:key="catalog-page-{{ $page }}" wire:click="setCatalogPage({{ $page }})" @if($page === $catalogCurrent) aria-current="page" @endif>{{ $page }}</button>
     @endfor
     <button type="button" wire:click="setCatalogPage({{ $catalogCurrent + 1 }})" @disabled($catalogCurrent >= $catalogPages) aria-label="Next page">›</button>
     <button type="button" wire:click="setCatalogPage({{ $catalogPages }})" @disabled($catalogCurrent >= $catalogPages) aria-label="Last page">»</button>
    </div>
    <label class="flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Items per page
     <select wire:change="setCatalogPageSize($event.target.value)" class="rounded-lg border border-violet-200 bg-white px-2 py-1.5 text-xs text-gray-900 dark:border-violet-800 dark:bg-gray-900 dark:text-white">
      @foreach([12, 24, 48] as $size)<option value="{{ $size }}" @selected($catalogVisible === $size)>{{ $size }}</option>@endforeach
     </select>
    </label>
   </nav>
  @else
   <div class="vx-empty"><x-heroicon-o-magnifying-glass class="mx-auto h-8 w-8" /><div class="mt-2 font-semibold text-gray-800 dark:text-gray-200">No matching inventory</div><div class="mt-1 text-sm">Try another search or stock filter.</div></div>
  @endif
 </div>
 @if($viewMode === 'table')
  <section class="vx-inventory-items-panel overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"><div class="hidden border-b border-gray-100 px-5 py-3 dark:border-gray-800 sm:block"><h2 class="text-base font-semibold">Inventory Table</h2><p class="mt-0.5 text-xs text-gray-500">Bulk actions, advanced filters, sorting and detailed columns.</p></div><div class="p-1 sm:p-2">{{ $this->table }}</div></section>
 @endif
</div>
{{-- Card view does not render the table that normally owns the action modals. --}}
<x-filament-actions::modals />
</div>
@endif
</x-filament-panels::page>

