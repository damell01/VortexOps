{{--
    The frame every report sits in: the report library on the left (a picker
    on phones) and the report itself on the right, plus the compare tray that
    collects streamers, channels, shows or formats picked with "+ Compare".
--}}
@php
    $catalog = \App\Support\ReportCatalog::forUser();
    $here = rtrim(strtok(request()->fullUrl(), '?'), '/');
    $compareUrl = \App\Filament\Pages\ReportCompare::canAccess() ? \App\Filament\Pages\ReportCompare::getUrl() : null;
@endphp
@php
    $overviewUrl = \App\Filament\Pages\ReportsOverview::getUrl();
    $libraryInitiallyOpen = rtrim(strtok($overviewUrl, '?'), '/') === $here;
    $activeReport = collect($catalog)->flatMap(fn ($group) => $group['items'])->first(fn ($item) => rtrim(strtok($item['url'], '?'), '/') === $here);
@endphp
<div class="vxrs vxrs--wide" x-data="{ libraryOpen: @js($libraryInitiallyOpen) }">
    <div class="vxrs-topnav vxrs-library-compact" :class="{ 'is-expanded': libraryOpen }">
        <div class="vxrs-library-header">
            <div class="vxrs-topnav-title">
                <x-filament::icon icon="heroicon-o-chart-bar-square" />
                <span x-text="libraryOpen ? 'Report library' : 'Reports'">Report library</span>
                <span class="vxrs-current-report" x-show="!libraryOpen" x-cloak>{{ $activeReport['label'] ?? 'Browse reports' }}</span>
            </div>
            <button type="button" class="vxrs-library-toggle" x-on:click="libraryOpen = !libraryOpen" :aria-expanded="libraryOpen.toString()" aria-controls="vxrs-library-grid">
                <x-filament::icon icon="heroicon-o-squares-2x2" />
                <span x-text="libraryOpen ? 'Collapse library' : 'Browse all reports'"></span>
                <x-filament::icon icon="heroicon-o-chevron-down" />
            </button>
        </div>
        <div id="vxrs-library-grid" x-show="libraryOpen" x-cloak x-transition.opacity.duration.150ms class="vxrs-library-grid">
            @foreach ($catalog as $g)
                <section class="vxrs-library-section" aria-label="{{ $g['group'] }} reports">
                    <h3>{{ $g['group'] }} <span>{{ count($g['items']) }} reports</span></h3>
                    <nav aria-label="{{ $g['group'] }} reports">
                        @foreach ($g['items'] as $item)
                            @php $on = rtrim(strtok($item['url'], '?'), '/') === $here; @endphp
                            <a href="{{ $item['url'] }}" wire:navigate @class(['vxrs-link', 'is-on' => $on]) @if($on) aria-current="page" @endif x-on:click="libraryOpen = false">
                                <span class="vxrs-ic"><x-filament::icon :icon="$item['icon']" /></span>
                                <span>{{ $item['label'] }}</span>
                                <x-filament::icon icon="heroicon-o-chevron-right" class="vxrs-link-chevron" />
                            </a>
                        @endforeach
                    </nav>
                </section>
            @endforeach
        </div>
    </div>
    <div class="vxrs-main">{{ $slot }}</div>
</div>

@if ($compareUrl && rtrim(strtok($compareUrl, '?'), '/') !== $here)
    <div class="vxrs-tray" x-data x-cloak x-show="$store.vxcmp && $store.vxcmp.items.length" x-transition.opacity.duration.200ms role="region" aria-label="Comparison">
        <b x-text="'Comparing ' + ($store.vxcmp?.kindLabel() ?? '')"></b>
        <template x-for="(it, i) in ($store.vxcmp?.items ?? [])" :key="it.id">
            <button type="button" class="vxrs-tc" x-on:click="$store.vxcmp.toggle($store.vxcmp.kind, it.id, it.name)" :title="'Remove ' + it.name">
                <i :style="'background:' + $store.vxcmp.color(i)"></i><span x-text="it.name"></span> ×
            </button>
        </template>
        <span x-show="$store.vxcmp?.items.length < 2" class="vxrs-tray-hint">Add one more</span>
        <a x-show="$store.vxcmp?.items.length >= 2" class="vxrs-go" :href="$store.vxcmp?.url('{{ $compareUrl }}')">Compare <span x-text="$store.vxcmp?.items.length"></span> →</a>
        <button type="button" class="vxrs-clr" x-on:click="$store.vxcmp.clear()">Clear</button>
    </div>
@endif

@once
<script>
(() => {
    const KIND = {streamer: 'streamers', channel: 'channels', show: 'shows', format: 'formats'};
    const COLORS = ['var(--vxc-1)', 'var(--vxc-2)', 'var(--vxc-3)', 'var(--vxc-4)'];
    const make = () => {
        let saved = {kind: null, items: []};
        try { saved = JSON.parse(localStorage.getItem('vx-compare') || '') || saved; } catch (e) {}
        return {
            kind: saved.kind, items: Array.isArray(saved.items) ? saved.items : [],
            save() { try { localStorage.setItem('vx-compare', JSON.stringify({kind: this.kind, items: this.items})); } catch (e) {} },
            has(kind, id) { return this.kind === kind && this.items.some((x) => String(x.id) === String(id)); },
            toggle(kind, id, name) {
                id = String(id);
                if (this.kind !== kind) { this.kind = kind; this.items = []; }
                if (this.has(kind, id)) this.items = this.items.filter((x) => String(x.id) !== id);
                else if (this.items.length < 4) this.items.push({id, name});
                this.save();
            },
            clear() { this.items = []; this.save(); },
            kindLabel() { return KIND[this.kind] ?? ''; },
            color(i) { return COLORS[i] ?? COLORS[0]; },
            url(base) { return base + '?kind=' + encodeURIComponent(this.kind) + '&items=' + this.items.map((x) => encodeURIComponent(x.id)).join(','); },
        };
    };
    const register = () => { if (window.Alpine && !Alpine.store('vxcmp')) Alpine.store('vxcmp', make()); };
    if (window.Alpine) register(); else document.addEventListener('alpine:init', register);

    // Headline figures count up once when a report opens.
    const countUp = () => {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        document.querySelectorAll('.vxrs-main [data-vx-countup]:not([data-vx-counted])').forEach((el) => {
            el.dataset.vxCounted = '1';
            const text = el.textContent.trim(); const m = text.match(/-?[\d,]+(\.\d+)?/);
            if (!m) return;
            const target = parseFloat(m[0].replace(/,/g, '')); if (!isFinite(target) || target === 0) return;
            const dp = (m[1] || '').length ? m[1].length - 1 : 0; const pre = text.slice(0, m.index), post = text.slice(m.index + m[0].length);
            const t0 = performance.now(), D = 650;
            const step = (t) => { const p = Math.min(1, (t - t0) / D), e = 1 - Math.pow(1 - p, 3);
                el.textContent = pre + (target * e).toLocaleString('en-US', {minimumFractionDigits: dp, maximumFractionDigits: dp}) + post;
                if (p < 1) requestAnimationFrame(step); else el.textContent = text; };
            requestAnimationFrame(step);
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', countUp); else setTimeout(countUp, 0);
})();
</script>
@endonce
