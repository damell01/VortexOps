<x-filament-panels::page>
@php
    $payload = $this->calendarPayload();
    $stats = $this->monthStats;
    $isAdmin = auth()->user()?->isAdmin();
    $todayIso = today()->toDateString();
    $hourPx = 52;
@endphp
{{--
    One data set, four renderings. Desktop (≥768px) renders Month / Week / List /
    Day server-side; the phone layout and the show drawer render from the same
    presented shows as JSON, so opening a show or swiping days is instant.
    wire:key carries a hash of that JSON: when the data changes, Alpine starts
    over from the new payload rather than holding stale shows.
--}}
<div class="vx-cal"
     wire:key="vx-cal-{{ md5(json_encode($payload)) }}"
     x-data="{
        d: @js($payload),
        by: {}, sel: null, openId: null, full: false, filters: false, slide: '',
        mview: window.__vxShowsMview || 'list', tx: 0, ty: 0, sy: 0,
        init() {
            const by = {};
            Object.values(this.d.shows).forEach(s => (by[s.date] ||= []).push(s));
            Object.values(by).forEach(a => a.sort((x, y) => x.sort - y.sort));
            this.by = by;
            this.sel = this.d.anchor;
            this.$watch('mview', v => window.__vxShowsMview = v);
        },
        get show() { return this.openId ? this.d.shows[this.openId] : null },
        open(id) { this.openId = id; this.full = false },
        close() { this.openId = null; this.full = false },
        day(s) { return this.by[s] || [] },
        dt(s) { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d) },
        iso(x) { return x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0') },
        add(s, n) { const x = this.dt(s); x.setDate(x.getDate() + n); return this.iso(x) },
        fmt(s, o) { return this.dt(s).toLocaleDateString('en-US', o) },
        select(s, dir = 0) {
            if (s < this.d.from || s > this.d.to) { $wire.goToDate(s); return }
            this.slide = dir > 0 ? 'vx-in-right' : (dir < 0 ? 'vx-in-left' : '');
            this.sel = s;
            $wire.anchor = s;
        },
        weekDays() {
            const start = this.add(this.sel, -this.dt(this.sel).getDay());
            return [0, 1, 2, 3, 4, 5, 6].map(i => { const s = this.add(start, i); return { s, wd: this.fmt(s, { weekday: 'short' }), n: this.dt(s).getDate(), count: this.day(s).length } });
        },
        monthCells() {
            const x = this.dt(this.sel), first = new Date(x.getFullYear(), x.getMonth(), 1), last = new Date(x.getFullYear(), x.getMonth() + 1, 0);
            const start = this.add(this.iso(first), -first.getDay()), total = Math.ceil((first.getDay() + last.getDate()) / 7) * 7;
            return Array.from({ length: total }, (_, i) => { const s = this.add(start, i); return { s, n: this.dt(s).getDate(), out: this.dt(s).getMonth() !== x.getMonth(), count: this.day(s).length } });
        },
        step(n) {
            if (this.mview === 'month') { const x = this.dt(this.sel); this.select(this.iso(new Date(x.getFullYear(), x.getMonth() + n, 1)), n) }
            else this.select(this.add(this.sel, 7 * n), n);
        },
        ts(e) { this.tx = e.touches[0].clientX; this.ty = e.touches[0].clientY },
        te(e, fn) { const dx = e.changedTouches[0].clientX - this.tx, dy = e.changedTouches[0].clientY - this.ty; if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) fn(dx < 0 ? 1 : -1) },
        addOn(date, time = null) { if (this.d.canAdd) $wire.mountAction('addShow', time ? { date, time } : { date }) },
        addAt(date, e, first) {
            const m = first * 60 + Math.floor(e.offsetY / {{ $hourPx }} * 2) * 30;
            this.addOn(date, String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'));
        },
        ds(e) { this.sy = e.touches[0].clientY },
        de(e) { const dy = e.changedTouches[0].clientY - this.sy; if (dy < -50) this.full = true; else if (dy > 70) { if (this.full) this.full = false; else this.close() } },
     }"
     x-on:keydown.escape.window="close()">

<style>
.vx-cal{--vx-bg:#fff;--vx-soft:#f8fafc;--vx-line:#e5e7eb;--vx-line2:#f1f5f9;--vx-text:#0f172a;--vx-muted:#64748b;--vx-faint:#94a3b8;--vx-p:var(--primary-600,#6d28d9);--vx-p-soft:color-mix(in srgb,var(--vx-p) 9%,transparent);min-width:0;color:var(--vx-text)}
.dark .vx-cal{--vx-bg:#0f172a;--vx-soft:#111c2f;--vx-line:#253247;--vx-line2:#1b2638;--vx-text:#e5e7eb;--vx-muted:#94a3b8;--vx-faint:#64748b}
.vx-cal [x-cloak]{display:none!important}
.vx-cal .vx-card{background:var(--vx-bg);border:1px solid var(--vx-line);border-radius:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.vx-cal button{cursor:pointer}
.vx-d{display:block}.vx-m{display:none}
@media(max-width:767px){.vx-d{display:none}.vx-m{display:block}}
/* KPIs */
.vx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.vx-kpi{display:flex;gap:14px;align-items:flex-start;padding:16px 18px;min-width:0}
.vx-kpi-ic{flex:none;display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:var(--vx-p-soft);color:var(--vx-p)}
.vx-kpi-v{font-size:1.45rem;font-weight:800;line-height:1.1;white-space:nowrap}
.vx-kpi-l{font-size:.8rem;font-weight:600;margin-top:2px}.vx-kpi-s{font-size:.72rem;color:var(--vx-faint);margin-top:4px}
@media(max-width:1100px){.vx-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
/* Toolbar */
.vx-bar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid var(--vx-line)}
.vx-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:0 12px;border:1px solid var(--vx-line);border-radius:10px;background:var(--vx-bg);font-size:.8rem;font-weight:600;color:var(--vx-text)}
.vx-btn:hover{background:var(--vx-soft)}.vx-btn.ic{width:36px;padding:0}
.vx-btn.on{border-color:var(--vx-p);color:var(--vx-p);background:var(--vx-p-soft)}
.vx-btn.primary{background:var(--vx-p);border-color:var(--vx-p);color:#fff}
.vx-title{font-size:1.35rem;font-weight:800;letter-spacing:-.01em;margin:0 6px}
.vx-seg{display:inline-flex;border:1px solid var(--vx-line);border-radius:10px;overflow:hidden;background:var(--vx-bg)}
.vx-seg button{min-height:34px;padding:0 16px;font-size:.8rem;font-weight:600;color:var(--vx-muted);border-left:1px solid var(--vx-line)}
.vx-seg button:first-child{border-left:0}.vx-seg button.on{background:var(--vx-p);color:#fff}
.vx-badge{display:inline-grid;place-items:center;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:var(--vx-p);color:#fff;font-size:.65rem;font-weight:800}
.vx-legend{display:flex;flex-wrap:wrap;gap:6px 14px;padding:10px 16px;border-bottom:1px solid var(--vx-line);font-size:.72rem;color:var(--vx-muted)}
.vx-dot{display:inline-block;width:8px;height:8px;border-radius:999px;flex:none}
.vx-filters{display:grid;grid-template-columns:minmax(0,2fr) repeat(3,minmax(0,1fr)) auto;gap:10px;align-items:end;padding:14px 16px;border-bottom:1px solid var(--vx-line);background:var(--vx-soft)}
.vx-filters label{font-size:.7rem;font-weight:600;color:var(--vx-muted);min-width:0}
.vx-filters input,.vx-filters select{margin-top:4px;width:100%;border-radius:10px;border:1px solid var(--vx-line);background:var(--vx-bg);font-size:.82rem;color:var(--vx-text)}
@media(max-width:1100px){.vx-filters{grid-template-columns:1fr 1fr}}
/* Shared show bits */
.vx-av{flex:none;display:grid;place-items:center;width:22px;height:22px;border-radius:999px;background:var(--vx-line2);color:var(--vx-muted);font-size:.62rem;font-weight:800;border:1px solid var(--vx-line)}
.vx-pill{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;font-size:.66rem;font-weight:700;white-space:nowrap}
.vx-pill.live{background:#fee2e2;color:#dc2626}.vx-pill.upcoming{background:#eef2ff;color:#4f46e5}.vx-pill.done{background:#dcfce7;color:#15803d}.vx-pill.cancelled{background:#f1f5f9;color:#64748b}
.dark .vx-pill.live{background:#451a1a;color:#fca5a5}.dark .vx-pill.upcoming{background:#1e1b4b;color:#a5b4fc}.dark .vx-pill.done{background:#052e1a;color:#86efac}.dark .vx-pill.cancelled{background:#1e293b;color:#94a3b8}
.vx-pill.live:before{content:"";width:6px;height:6px;border-radius:999px;background:currentColor;animation:vxPulse 1.4s infinite}
@keyframes vxPulse{50%{opacity:.3}}
/* Month */
.vx-dows{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border-bottom:1px solid var(--vx-line)}
.vx-dows div{padding:9px 0;text-align:center;font-size:.7rem;font-weight:700;letter-spacing:.06em;color:var(--vx-muted)}
.vx-month{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
.vx-cell{position:relative;min-width:0;height:172px;padding:6px 6px 4px;border-right:1px solid var(--vx-line);border-bottom:1px solid var(--vx-line);overflow:hidden;display:flex;flex-direction:column;gap:3px}
.vx-cell:nth-child(7n){border-right:0}
.vx-cell.out{background:var(--vx-soft)}.vx-cell.out .vx-num{color:var(--vx-faint)}
.vx-cell.add{cursor:copy}.vx-cell.add:hover{background:color-mix(in srgb,var(--vx-p) 4%,transparent)}
.vx-cell.today{box-shadow:inset 0 0 0 2px var(--vx-p);border-radius:10px;background:var(--vx-p-soft)}
.vx-cell-h{display:flex;align-items:center;justify-content:space-between;min-height:22px}
.vx-num{font-size:.78rem;font-weight:700;padding:2px 6px;border-radius:7px;color:var(--vx-text)}
.vx-num:hover{background:var(--vx-line2);color:var(--vx-p)}
.vx-today-tag{font-size:.62rem;font-weight:800;color:#fff;background:var(--vx-p);border-radius:999px;padding:2px 8px}
.vx-chip{display:flex;align-items:center;gap:6px;width:100%;min-width:0;padding:4px 6px;border:1px solid var(--vx-line);border-left:3px solid var(--c);border-radius:8px;background:var(--vx-bg);text-align:left}
.vx-chip:hover{border-color:var(--c);box-shadow:0 2px 8px rgba(15,23,42,.08)}
.vx-chip .tm{font-size:.62rem;color:var(--vx-muted);line-height:1.2}.vx-chip .tt{font-size:.74rem;font-weight:600;line-height:1.25;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vx-chip .tx{min-width:0;flex:1}
.vx-chip.cancelled{opacity:.55}.vx-chip.cancelled .tt{text-decoration:line-through}
.vx-more{align-self:flex-start;font-size:.7rem;font-weight:700;color:var(--vx-p);padding:1px 6px;border-radius:6px}.vx-more:hover{background:var(--vx-p-soft)}
/* Week */
.vx-week-h,.vx-week-b,.vx-week-u{display:grid;grid-template-columns:58px repeat(7,minmax(0,1fr))}
.vx-week-h{border-bottom:1px solid var(--vx-line)}
.vx-week-h>div{padding:8px 4px;text-align:center;font-size:.7rem;font-weight:700;color:var(--vx-muted)}
.vx-week-h b{display:block;font-size:1.1rem;color:var(--vx-text)}
.vx-week-h .is-today{background:var(--vx-p-soft);color:var(--vx-p)}.vx-week-h .is-today b{color:var(--vx-p)}
.vx-week-u{border-bottom:1px solid var(--vx-line)}.vx-week-u>div{padding:4px;border-left:1px solid var(--vx-line2);display:flex;flex-direction:column;gap:3px;min-width:0}
.vx-hours{position:relative}.vx-hours span{position:absolute;right:8px;font-size:.66rem;color:var(--vx-faint);transform:translateY(-50%)}
.vx-wcol{position:relative;border-left:1px solid var(--vx-line2);background-image:repeating-linear-gradient(to bottom,var(--vx-line2) 0 1px,transparent 1px {{ $hourPx }}px)}
.vx-wcol.add{cursor:copy}.vx-wcol.is-today{background-color:color-mix(in srgb,var(--vx-p) 4%,transparent)}
.vx-ev{position:absolute;padding:4px 6px;border-radius:8px;border-left:3px solid var(--c);background:color-mix(in srgb,var(--c) 13%,var(--vx-bg));overflow:hidden;text-align:left;font-size:.7rem;line-height:1.25}
.vx-ev:hover{box-shadow:0 3px 10px rgba(15,23,42,.14);z-index:2}
.vx-ev b{display:block;font-size:.72rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vx-ev span{color:var(--vx-muted)}
/* List / Day */
.vx-agenda-day{padding:14px 16px;border-bottom:1px solid var(--vx-line)}
.vx-agenda-h{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;font-size:.74rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase}
.vx-agenda-h span{font-weight:600;letter-spacing:0;text-transform:none;color:var(--vx-muted)}
.vx-arow{display:grid;grid-template-columns:84px minmax(0,1.5fr) minmax(0,1fr) auto;gap:12px;align-items:center;width:100%;padding:8px 10px;border-radius:10px;text-align:left}
.vx-arow:hover{background:var(--vx-soft)}
.vx-arow .tm{font-size:.78rem;font-weight:600;color:var(--vx-muted)}
.vx-arow .tt{display:flex;align-items:center;gap:8px;min-width:0;font-size:.85rem;font-weight:700}.vx-arow .tt span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vx-arow .who{display:flex;align-items:center;gap:6px;min-width:0;font-size:.8rem;color:var(--vx-muted)}
.vx-tl{padding:8px 16px 16px}
.vx-tl-row{display:grid;grid-template-columns:78px minmax(0,1fr);gap:14px;padding:6px 0}
.vx-tl-time{padding-top:12px;text-align:right;font-size:.8rem;font-weight:700}.vx-tl-time small{display:block;font-weight:500;color:var(--vx-faint)}
.vx-tl-card{display:flex;align-items:center;gap:14px;width:100%;padding:12px 14px;border:1px solid var(--vx-line);border-left:4px solid var(--c);border-radius:12px;background:var(--vx-bg);text-align:left}
.vx-tl-card:hover{box-shadow:0 4px 14px rgba(15,23,42,.08)}
.vx-cover{flex:none;width:52px;height:52px;border-radius:10px;object-fit:cover;background:var(--vx-line2)}
.vx-empty{padding:48px 16px;text-align:center;color:var(--vx-muted);font-size:.86rem}
/* Drawer: right panel on desktop, bottom sheet on phones */
.vx-drawer{position:fixed;z-index:45;top:0;right:0;bottom:0;width:410px;max-width:100vw;display:flex;flex-direction:column;background:var(--vx-bg);border-left:1px solid var(--vx-line);box-shadow:-12px 0 40px rgba(15,23,42,.14);transition:transform .25s ease,height .25s ease}
.vx-drawer-scroll{flex:1;overflow-y:auto;overscroll-behavior:contain}
.vx-drawer-hero{position:relative;height:150px;background:linear-gradient(135deg,var(--c),color-mix(in srgb,var(--c) 40%,#0f172a))}
.vx-drawer-hero img{width:100%;height:100%;object-fit:cover}
.vx-drawer-x{position:absolute;top:10px;right:10px;display:grid;place-items:center;width:34px;height:34px;border-radius:999px;background:rgba(255,255,255,.92);color:#0f172a;box-shadow:0 2px 6px rgba(0,0,0,.15)}
.vx-drawer-hero .vx-pill{position:absolute;top:12px;left:12px}
.vx-handle{display:none}
.vx-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
.vx-stats>div{border:1px solid var(--vx-line);border-radius:12px;padding:10px 8px;min-width:0}
.vx-stats b{display:block;font-size:1rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.vx-stats span{font-size:.66rem;color:var(--vx-muted)}
.vx-kv{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-top:1px solid var(--vx-line2);font-size:.8rem}
.vx-kv>span:first-child{color:var(--vx-muted)}.vx-kv>span:last-child{font-weight:600;text-align:right;min-width:0;overflow-wrap:anywhere}
.vx-ok{color:#16a34a}.vx-wait{color:var(--vx-faint)}
.vx-backdrop{display:none}
.vx-m-only{display:none!important}
.vx-drawer.vx-drawer-off{transform:translateX(100%)}
@media(max-width:767px){
 .vx-drawer{top:auto;left:0;width:auto;height:70vh;border-left:0;border-radius:22px 22px 0 0;box-shadow:0 -12px 40px rgba(15,23,42,.25);z-index:60}
 .vx-drawer.full{height:calc(100dvh - 16px)}
 .vx-handle{display:flex;justify-content:center;padding:10px 0 6px;touch-action:none}
 .vx-handle i{width:44px;height:5px;border-radius:999px;background:var(--vx-line)}
 .vx-drawer-hero{display:none}
 .vx-m-only{display:inline-flex!important}div.vx-m-only{display:block!important}
 .vx-backdrop{display:block;position:fixed;inset:0;z-index:59;background:rgba(15,23,42,.45)}
 .vx-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.vx-stats>div:nth-child(4){display:none}
 .vx-drawer.vx-drawer-off{transform:translateY(100%)}
}
/* Phone layout */
.vx-m-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}
.vx-m-head h2{font-size:1rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
.vx-m .vx-seg{display:flex;width:100%}.vx-m .vx-seg button{flex:1}
.vx-strip{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px;margin:12px 0 4px;touch-action:pan-y}
.vx-sday{display:flex;flex-direction:column;align-items:center;gap:2px;padding:7px 0 6px;border-radius:12px;min-width:0}
.vx-sday .wd{font-size:.62rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--vx-muted)}
.vx-sday .n{font-size:1.05rem;font-weight:800}.vx-sday .c{font-size:.6rem;color:var(--vx-faint);min-height:12px}
.vx-sday.today .n{color:var(--vx-p)}
.vx-sday.on{background:var(--vx-p);box-shadow:0 4px 12px color-mix(in srgb,var(--vx-p) 35%,transparent)}.vx-sday.on *{color:#fff!important}
.vx-mgrid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:2px;margin:12px 0 4px;touch-action:pan-y}
.vx-mgrid .h{text-align:center;font-size:.6rem;font-weight:700;color:var(--vx-muted);padding:4px 0}
.vx-mcell{display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 0;border-radius:10px;font-size:.85rem;font-weight:700}
.vx-mcell.out{color:var(--vx-faint);font-weight:500}.vx-mcell.today{color:var(--vx-p)}.vx-mcell.on{background:var(--vx-p);color:#fff}
.vx-mcell i{width:5px;height:5px;border-radius:999px;background:var(--vx-p)}.vx-mcell.on i{background:#fff}
.vx-mday-h{display:flex;justify-content:space-between;align-items:baseline;margin:14px 2px 8px}
.vx-mday-h b{font-size:.98rem}.vx-mday-h span{font-size:.78rem;color:var(--vx-muted);font-weight:600}
.vx-acard{display:flex;align-items:center;gap:10px;width:100%;margin-bottom:10px;padding:12px 12px 12px 14px;border:1px solid var(--vx-line);border-left:4px solid var(--c);border-radius:14px;background:var(--vx-bg);text-align:left;box-shadow:0 1px 2px rgba(15,23,42,.04)}
.vx-acard .tm{font-size:.74rem;font-weight:600;color:var(--vx-muted)}
.vx-acard .tt{display:flex;align-items:center;gap:7px;margin-top:4px;font-size:.92rem;font-weight:800;min-width:0}.vx-acard .tt span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vx-acard .who{display:flex;align-items:center;gap:6px;margin-top:6px;font-size:.78rem;color:var(--vx-muted)}
.vx-in-right{animation:vxInR .22s ease}.vx-in-left{animation:vxInL .22s ease}
@keyframes vxInR{from{opacity:.3;transform:translateX(28px)}}@keyframes vxInL{from{opacity:.3;transform:translateX(-28px)}}
@media(prefers-reduced-motion:reduce){.vx-in-right,.vx-in-left,.vx-pill.live:before{animation:none}.vx-drawer{transition:none}}
</style>

@if(auth()->user()->isStreamer() && ! $isAdmin && auth()->user()->streamer)
    <div class="mb-4">@livewire('create-manual-show', ['streamer' => auth()->user()->streamer])</div>
@endif

{{-- ── KPIs (desktop) ─────────────────────────────────────────────── --}}
<div class="vx-d">
<div class="vx-kpis">
    <div class="vx-card vx-kpi"><div class="vx-kpi-ic"><x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5" /></div><div class="min-w-0"><div class="vx-kpi-v">{{ number_format($stats['total']) }}</div><div class="vx-kpi-l">Total Shows</div><div class="vx-kpi-s">{{ $stats['label'] }}</div></div></div>
    <div class="vx-card vx-kpi"><div class="vx-kpi-ic" style="background:#dcfce7;color:#16a34a"><x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5" /></div><div class="min-w-0 flex-1"><div class="vx-kpi-v">{{ number_format($stats['completed']) }}</div><div class="vx-kpi-l">Completed</div><div class="vx-kpi-s">Report workflow done</div></div>
        <svg viewBox="0 0 36 36" width="46" height="46" aria-label="{{ $stats['completedPct'] }}% completed" role="img" style="flex:none"><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--vx-line)" stroke-width="3.5"/><circle cx="18" cy="18" r="15.5" fill="none" stroke="var(--vx-p)" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="{{ round($stats['completedPct'] * 0.974, 1) }} 100" transform="rotate(-90 18 18)"/><text x="18" y="21" text-anchor="middle" font-size="8.5" font-weight="800" fill="currentColor">{{ $stats['completedPct'] }}%</text></svg></div>
    <div class="vx-card vx-kpi"><div class="vx-kpi-ic" style="background:#d1fae5;color:#059669"><x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5" /></div><div class="min-w-0"><div class="vx-kpi-v">${{ number_format($stats['gross'], 0) }}</div><div class="vx-kpi-l">Whatnot Gross</div><div class="vx-kpi-s">{{ $stats['label'] }}</div></div></div>
    <div class="vx-card vx-kpi"><div class="vx-kpi-ic" style="background:#e0e7ff;color:#4f46e5"><x-filament::icon icon="heroicon-o-chart-bar" class="h-5 w-5" /></div><div class="min-w-0"><div class="vx-kpi-v">${{ number_format($stats['net'], 0) }}</div><div class="vx-kpi-l">Est. Whatnot Net</div><div class="vx-kpi-s">{{ $stats['label'] }}</div></div></div>
</div>
</div>

{{-- ── Filters (shared by both layouts) ──────────────────────────── --}}
@php $filterCount = $this->activeFilterCount(); @endphp

{{-- ── Desktop calendar ──────────────────────────────────────────── --}}
<section class="vx-d vx-card" style="overflow:hidden">
    <div class="vx-bar">
        <button type="button" class="vx-btn ic" wire:click="goPrevious" aria-label="Previous"><x-filament::icon icon="heroicon-m-chevron-left" class="h-4 w-4" /></button>
        <button type="button" class="vx-btn" wire:click="goToToday">Today</button>
        <button type="button" class="vx-btn ic" wire:click="goNext" aria-label="Next"><x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4" /></button>
        <h2 class="vx-title">{{ $this->calendarTitle() }}</h2>
        <div wire:loading.delay class="text-xs" style="color:var(--vx-faint)">Loading…</div>
        <div class="ml-auto flex items-center gap-2">
            <div class="vx-seg" role="group" aria-label="Calendar view">
                @foreach(['month' => 'Month', 'week' => 'Week', 'list' => 'List'] as $v => $label)
                    <button type="button" wire:click="setView('{{ $v }}')" @class(['on' => $viewMode === $v]) aria-pressed="{{ $viewMode === $v ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            <button type="button" class="vx-btn" :class="filters && 'on'" @click="filters = !filters" :aria-expanded="filters.toString()"><x-filament::icon icon="heroicon-o-funnel" class="h-4 w-4" />Filters @if($filterCount)<span class="vx-badge">{{ $filterCount }}</span>@endif</button>
        </div>
    </div>
    @include('filament.pages.partials.shows-filters')
    @if(($legend = $this->channelLegend())->isNotEmpty())
        <div class="vx-legend">@foreach($legend as $c)<span class="inline-flex items-center gap-1.5"><span class="vx-dot" style="background:{{ $c['color'] }}"></span>{{ $c['name'] }}</span>@endforeach</div>
    @endif

    @if($viewMode === 'month')
        <div class="vx-dows">@foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)<div>{{ strtoupper($d) }}</div>@endforeach</div>
        <div class="vx-month">
            @foreach($this->monthWeeks() as $week)
                @foreach($week as $cell)
                    @php $iso = $cell['date']->toDateString(); $count = $cell['shows']->count(); $isToday = $iso === $todayIso; @endphp
                    <div wire:key="cell-{{ $iso }}" @class(['vx-cell', 'out' => ! $cell['inMonth'], 'today' => $isToday, 'add' => $isAdmin])
                         @if($isAdmin) @click="addOn('{{ $iso }}')" title="Add a show on {{ $cell['date']->format('M j') }}" @endif>
                        <div class="vx-cell-h">
                            <button type="button" class="vx-num" wire:click.stop="openDay('{{ $iso }}')" @click.stop aria-label="Open {{ $cell['date']->format('l, F j') }}">{{ $cell['date']->day === 1 || $loop->parent->first && $loop->first ? $cell['date']->format('M j') : $cell['date']->day }}</button>
                            @if($isToday)<span class="vx-today-tag">Today</span>@endif
                        </div>
                        @foreach($cell['shows']->take(3) as $p)
                            <button type="button" class="vx-chip {{ $p['state'] }}" style="--c:{{ $p['color'] }}" @click.stop="open({{ $p['id'] }})" title="{{ $p['title'] }}">
                                <span class="tx"><span class="tm block">{{ $p['time'] }}</span><span class="tt block">{{ $p['title'] }}</span></span>
                                @if($p['state'] === 'live')<span class="vx-pill live">Live</span>@else<span class="vx-av" title="{{ $p['streamers'] ?? 'Unassigned' }}">{{ $p['streamers'] ? $p['initial'] : '?' }}</span>@endif
                            </button>
                        @endforeach
                        @if($count > 3)
                            <button type="button" class="vx-more" wire:click.stop="openDay('{{ $iso }}')" @click.stop>+{{ $count - 3 }} more</button>
                        @endif
                    </div>
                @endforeach
            @endforeach
        </div>

    @elseif($viewMode === 'week')
        @php $wk = $this->weekGrid(); $rows = $wk['last'] - $wk['first']; @endphp
        <div class="vx-week-h"><div></div>@foreach($wk['columns'] as $col)<div @class(['is-today' => $col['date']->toDateString() === $todayIso])>{{ strtoupper($col['date']->format('D')) }}<b>{{ $col['date']->day }}</b></div>@endforeach</div>
        @if($wk['hasUntimed'])
            <div class="vx-week-u"><div class="vx-hours" style="font-size:.62rem;color:var(--vx-faint);padding:8px 6px;text-align:right">Time TBD</div>
                @foreach($wk['columns'] as $col)<div>@foreach($col['untimed'] as $p)<button type="button" class="vx-chip" style="--c:{{ $p['color'] }}" @click="open({{ $p['id'] }})"><span class="tx"><span class="tt block">{{ $p['title'] }}</span></span></button>@endforeach</div>@endforeach
            </div>
        @endif
        <div style="max-height:720px;overflow-y:auto">
            <div class="vx-week-b" style="height:{{ $rows * $hourPx }}px">
                <div class="vx-hours">@for($h = $wk['first'] + 1; $h < $wk['last']; $h++)<span style="top:{{ ($h - $wk['first']) * $hourPx }}px">{{ \Carbon\Carbon::createFromTime($h % 24)->format('g A') }}</span>@endfor</div>
                @foreach($wk['columns'] as $col)
                    @php $iso = $col['date']->toDateString(); @endphp
                    <div @class(['vx-wcol', 'add' => $isAdmin, 'is-today' => $iso === $todayIso]) @if($isAdmin) @click.self="addAt('{{ $iso }}', $event, {{ $wk['first'] }})" @endif>
                        @foreach($col['timed'] as $p)
                            <button type="button" class="vx-ev" style="--c:{{ $p['color'] }};top:{{ $p['top'] / 60 * $hourPx + 1 }}px;height:{{ max(26, $p['height'] / 60 * $hourPx - 2) }}px;left:calc({{ $p['lane'] }} * 100% / {{ $p['lanes'] }} + 2px);width:calc(100% / {{ $p['lanes'] }} - 4px)" @click="open({{ $p['id'] }})" title="{{ $p['title'] }} · {{ $p['timeRange'] }}">
                                <span>{{ $p['time'] }}</span><b>{{ $p['title'] }}</b>@if($p['streamers'])<span>{{ $p['streamers'] }}</span>@endif
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

    @elseif($viewMode === 'list')
        @forelse($this->agendaDays() as $iso => $shows)
            @php $date = \Carbon\Carbon::parse($iso); @endphp
            <div class="vx-agenda-day" wire:key="agenda-{{ $iso }}">
                <div class="vx-agenda-h"><button type="button" wire:click="openDay('{{ $iso }}')" class="hover:underline" @if($iso === $todayIso) style="color:var(--vx-p)" @endif>{{ $date->format('D, M j') }}@if($iso === $todayIso) · Today @endif</button><span>{{ $shows->count() }} {{ Str::plural('show', $shows->count()) }}</span></div>
                @foreach($shows->take(4) as $p)
                    <button type="button" class="vx-arow" @click="open({{ $p['id'] }})">
                        <span class="tm">{{ $p['time'] }}</span>
                        <span class="tt"><span class="vx-dot" style="background:{{ $p['color'] }}"></span><span>{{ $p['title'] }}</span></span>
                        <span class="who"><span class="vx-av">{{ $p['streamers'] ? $p['initial'] : '?' }}</span><span class="truncate">{{ $p['streamers'] ?? 'Unassigned' }}</span></span>
                        <span class="vx-pill {{ $p['state'] }}">{{ $p['stateLabel'] }}</span>
                    </button>
                @endforeach
                @if($shows->count() > 4)<button type="button" class="vx-more" style="margin-left:96px" wire:click="openDay('{{ $iso }}')">+{{ $shows->count() - 4 }} more</button>@endif
            </div>
        @empty
            <div class="vx-empty">No shows in {{ $this->anchorDate()->format('F Y') }}{{ $filterCount ? ' match these filters' : '' }}.</div>
        @endforelse

    @else
        @php $shows = $this->dayShows(); $a = $this->anchorDate(); @endphp
        <div class="vx-bar" style="justify-content:space-between">
            <div><div class="text-xs font-extrabold uppercase tracking-wider" style="color:var(--vx-muted)">{{ $a->format('l') }}</div><div class="text-lg font-extrabold">{{ $a->format('F j') }} <span class="text-sm font-semibold" style="color:var(--vx-muted)">· {{ $shows->count() }} {{ Str::plural('show', $shows->count()) }}</span></div></div>
            @if($isAdmin)<button type="button" class="vx-btn primary" @click="addOn('{{ $a->toDateString() }}')"><x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />Add show</button>@endif
        </div>
        <div class="vx-tl">
            @forelse($shows as $p)
                <div class="vx-tl-row">
                    <div class="vx-tl-time">{{ $p['time'] }}@if($p['duration'])<small>{{ $p['duration'] }}</small>@endif</div>
                    <button type="button" class="vx-tl-card" style="--c:{{ $p['color'] }}" @click="open({{ $p['id'] }})">
                        @if($p['cover'])<img class="vx-cover" src="{{ $p['cover'] }}" alt="" loading="lazy">@endif
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-bold">{{ $p['title'] }}</span>
                            <span class="mt-1 flex items-center gap-2 text-xs" style="color:var(--vx-muted)"><span class="vx-av">{{ $p['streamers'] ? $p['initial'] : '?' }}</span>{{ $p['streamers'] ?? 'Unassigned' }} · {{ $p['timeRange'] }}@if($p['channel']) · <span class="vx-dot" style="background:{{ $p['color'] }}"></span>{{ $p['channel'] }}@endif</span>
                        </span>
                        <span class="vx-pill {{ $p['state'] }}">{{ $p['stateLabel'] }}</span>
                    </button>
                </div>
            @empty
                <div class="vx-empty">No shows scheduled for {{ $a->format('l, F j') }}.@if($isAdmin)<div class="mt-3"><button type="button" class="vx-btn primary" @click="addOn('{{ $a->toDateString() }}')">Add a show</button></div>@endif</div>
            @endforelse
        </div>
    @endif
</section>

{{-- ── Phone: date swiper + vertical agenda ──────────────────────── --}}
<section class="vx-m">
    <div class="vx-m-head">
        <button type="button" class="vx-btn ic" @click="step(-1)" aria-label="Previous"><x-filament::icon icon="heroicon-m-chevron-left" class="h-4 w-4" /></button>
        <h2 x-text="sel && fmt(sel, { month: 'long', year: 'numeric' })"></h2>
        <button type="button" class="vx-btn ic" @click="step(1)" aria-label="Next"><x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4" /></button>
    </div>
    <div class="flex items-center gap-2">
        <div class="vx-seg flex-1" role="group" aria-label="Calendar view">
            <button type="button" :class="mview === 'month' && 'on'" @click="mview = 'month'">Month</button>
            <button type="button" :class="mview === 'week' && 'on'" @click="mview = 'week'">Week</button>
            <button type="button" :class="mview === 'list' && 'on'" @click="mview = 'list'">List</button>
        </div>
        <button type="button" class="vx-btn" @click="select(d.today)" x-show="sel !== d.today">Today</button>
        <button type="button" class="vx-btn ic" :class="filters && 'on'" @click="filters = !filters" aria-label="Filters" style="position:relative"><x-filament::icon icon="heroicon-o-funnel" class="h-4 w-4" />@if($filterCount)<span class="vx-badge" style="position:absolute;top:-6px;right:-6px">{{ $filterCount }}</span>@endif</button>
    </div>
    <div class="mt-3 vx-card" x-show="filters" x-cloak style="overflow:hidden">@include('filament.pages.partials.shows-filters', ['mobile' => true])</div>

    <template x-if="mview !== 'month'">
        <div class="vx-strip" @touchstart.passive="ts($event)" @touchend="te($event, n => select(add(sel, 7 * n), n))">
            <template x-for="w in weekDays()" :key="w.s">
                <button type="button" class="vx-sday" :class="{ on: w.s === sel, today: w.s === d.today }" @click="select(w.s, w.s > sel ? 1 : -1)" :aria-label="fmt(w.s, { weekday: 'long', month: 'long', day: 'numeric' }) + ', ' + w.count + ' shows'">
                    <span class="wd" x-text="w.wd"></span><span class="n" x-text="w.n"></span><span class="c" x-text="w.count ? w.count : ''"></span>
                </button>
            </template>
        </div>
    </template>
    <template x-if="mview === 'month'">
        <div class="vx-mgrid" @touchstart.passive="ts($event)" @touchend="te($event, n => step(n))">
            <template x-for="h in ['S','M','T','W','T','F','S']"><div class="h" x-text="h"></div></template>
            <template x-for="c in monthCells()" :key="c.s">
                <button type="button" class="vx-mcell" :class="{ out: c.out, on: c.s === sel, today: c.s === d.today }" @click="select(c.s)"><span x-text="c.n"></span><i :style="c.count ? '' : 'visibility:hidden'"></i></button>
            </template>
        </div>
    </template>

    <div @touchstart.passive="ts($event)" @touchend="te($event, n => select(add(sel, mview === 'week' ? 7 * n : n), n))" style="min-height:40vh">
        <template x-for="k in [sel + mview]" :key="k">
            <div :class="slide">
                <template x-for="s in (mview === 'week' ? weekDays().map(w => w.s) : [sel])" :key="s">
                    <div x-show="mview !== 'week' || day(s).length">
                        <div class="vx-mday-h"><b x-text="fmt(s, { weekday: 'long', month: 'long', day: 'numeric' })"></b><span x-text="day(s).length + (day(s).length === 1 ? ' show' : ' shows')"></span></div>
                        <template x-for="p in day(s)" :key="p.id">
                            <button type="button" class="vx-acard" :style="'--c:' + p.color" @click="open(p.id)">
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center justify-between gap-2"><span class="tm" x-text="p.timeRange"></span><span class="vx-pill" :class="p.state" x-text="p.stateLabel"></span></span>
                                    <span class="tt"><span class="vx-dot" :style="'background:' + p.color"></span><span x-text="p.title"></span></span>
                                    <span class="who"><span class="vx-av" x-text="p.streamers ? p.initial : '?'"></span><span x-text="p.streamers || 'Unassigned'"></span></span>
                                </span>
                                <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" style="color:var(--vx-faint)" />
                            </button>
                        </template>
                        <div x-show="!day(s).length" class="vx-card vx-empty" style="padding:28px 16px">
                            No shows this day.
                            <template x-if="d.canAdd"><div class="mt-3"><button type="button" class="vx-btn primary" @click="addOn(s)">Add a show</button></div></template>
                        </div>
                    </div>
                </template>
                <div x-show="mview === 'week' && !weekDays().some(w => w.count)" class="vx-card vx-empty" style="margin-top:14px">No shows this week.</div>
            </div>
        </template>
    </div>
</section>

{{-- ── Show drawer / bottom sheet ────────────────────────────────── --}}
<div class="vx-backdrop" x-show="show" x-cloak x-transition.opacity @click="close()"></div>
<aside class="vx-drawer" x-show="show" x-cloak role="dialog" aria-modal="false" :aria-label="show && show.title" :class="full && 'full'" :style="show && ('--c:' + show.color)"
       x-transition:enter-start="vx-drawer-off" x-transition:leave-end="vx-drawer-off">
    <template x-if="show">
        <div class="flex min-h-0 flex-1 flex-col">
            <div class="vx-handle" @touchstart.passive="ds($event)" @touchend="de($event)" @click="full = !full"><i></i></div>
            <div class="vx-drawer-scroll">
                <div class="vx-drawer-hero">
                    <template x-if="show.cover"><img :src="show.cover" alt=""></template>
                    <span class="vx-pill" :class="show.state" x-text="show.state === 'live' ? 'Live now' : show.stateLabel"></span>
                    <button type="button" class="vx-drawer-x" @click="close()" aria-label="Close"><x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" /></button>
                </div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-xl font-extrabold leading-tight" x-text="show.title"></h3>
                        <template x-if="show.whatnotUrl"><a :href="show.whatnotUrl" target="_blank" rel="noopener" class="mt-1 shrink-0" style="color:var(--vx-p)" aria-label="Open on Whatnot"><x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="h-5 w-5" /></a></template>
                        <button type="button" class="vx-btn ic vx-m-only" @click="close()" aria-label="Close"><x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" /></button>
                    </div>
                    <div class="mt-2 vx-m-only"><span class="vx-pill" :class="show.state" x-text="show.state === 'live' ? 'Live now' : show.stateLabel"></span></div>
                    <div class="mt-3 space-y-2 text-sm" style="color:var(--vx-muted)">
                        <div class="flex items-center gap-2"><x-filament::icon icon="heroicon-o-calendar" class="h-4 w-4" /><span x-text="show.dateLabel + ' · ' + show.timeRange + (show.duration ? ' (' + show.duration + ')' : '')"></span></div>
                        <div class="flex items-center gap-2" x-show="show.channel"><span class="vx-dot" :style="'background:' + show.color"></span><span x-text="show.channel"></span></div>
                        <div class="flex items-center gap-2"><span class="vx-av" x-text="show.streamers ? show.initial : '?'"></span><span class="font-semibold" style="color:var(--vx-text)" x-text="show.streamers || 'Unassigned'"></span></div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <template x-if="show.whatnotUrl"><a :href="show.whatnotUrl" target="_blank" rel="noopener" class="vx-btn on"><x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="h-4 w-4" />View on Whatnot</a></template>
                        <template x-if="show.editUrl"><a :href="show.editUrl" wire:navigate class="vx-btn" :class="!show.whatnotUrl && 'col-span-2'"><x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />Edit Show</a></template>
                    </div>
                    <div class="vx-stats mt-5">
                        <div><b x-text="show.gross"></b><span>Gross Sales</span></div>
                        <div><b x-text="show.net"></b><span>Est. Net</span></div>
                        <div><b x-text="show.units"></b><span>Items Sold</span></div>
                        <div><b x-text="show.views"></b><span>Views</span></div>
                    </div>
                    <div class="mt-5">
                        <div class="vx-kv"><span>Analytics</span><span :class="show.analyticsOk ? 'vx-ok' : 'vx-wait'" x-text="show.analytics + (show.analyticsOk ? ' ✓' : '')"></span></div>
                        <div class="vx-kv"><span>Orders</span><span :class="show.ordersOk ? 'vx-ok' : 'vx-wait'" x-text="show.orders + (show.ordersOk ? ' ✓' : '')"></span></div>
                        <div class="vx-kv"><span>Inventory</span><span :class="show.inventoryOk ? 'vx-ok' : 'vx-wait'" x-text="show.inventory + (show.inventoryOk ? ' ✓' : '')"></span></div>
                        <div class="vx-kv"><span>Report</span><span x-text="show.workflow"></span></div>
                        <div class="vx-kv" x-show="show.whatnotId"><span>Whatnot show ID</span><span x-text="show.whatnotId"></span></div>
                    </div>
                </div>
            </div>
            <div class="p-4" style="border-top:1px solid var(--vx-line)">
                <a :href="show.viewUrl" wire:navigate class="vx-btn primary w-full" style="min-height:44px">View Full Show Details <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" /></a>
            </div>
        </div>
    </template>
</aside>
</div>
</x-filament-panels::page>
