<x-filament-panels::page>
@php
    $pages = $this->pages();
    $total = count($pages);

    // Pages that open a part of the book, for the jump menu. Read off the
    // contents page so the menu and the contents cannot disagree.
    $chapters = collect($pages[1]['entries'] ?? [])->prepend(['label' => 'Cover', 'page' => 0])->prepend(['label' => 'Contents', 'page' => 1])->sortBy('page')->values();

    static $sizes = [];
@endphp

{{-- Styles live here rather than in the theme so the pilot works without a
     front-end rebuild. Everything is scoped under .vx-fb. --}}
<style>
    .vx-fb{--fb-paper:#fffdf8;--fb-ink:#1f2937;--fb-muted:#6b7280;--fb-rule:#e7e2d6;--fb-accent:var(--primary-600,#4f46e5);--fb-turn:.75s}
    .vx-fb-bar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem}
    .vx-fb-bar .vx-fb-group{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem}
    .vx-fb-btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;min-height:2.5rem;padding:0 .9rem;border-radius:.6rem;border:1px solid rgb(209 213 219);background:#fff;color:#374151;font-size:.875rem;font-weight:600;transition:background .15s}
    .vx-fb-btn:hover:not(:disabled){background:#f9fafb}
    .vx-fb-btn:disabled{opacity:.4;cursor:not-allowed}
    .vx-fb-btn.is-primary{background:var(--fb-accent);border-color:var(--fb-accent);color:#fff}
    .vx-fb-btn.is-primary:hover:not(:disabled){filter:brightness(1.08);background:var(--fb-accent)}
    .dark .vx-fb-btn{background:rgb(17 24 39);border-color:rgb(55 65 81);color:rgb(229 231 235)}
    .dark .vx-fb-btn.is-primary{background:var(--fb-accent);color:#fff}
    .vx-fb-select{min-height:2.5rem;max-width:16rem;border-radius:.6rem;border:1px solid rgb(209 213 219);background:#fff;padding:0 2rem 0 .75rem;font-size:.875rem;color:#374151}
    .dark .vx-fb-select{background:rgb(17 24 39);border-color:rgb(55 65 81);color:rgb(229 231 235)}
    .vx-fb-count{font-size:.8rem;color:var(--fb-muted);font-variant-numeric:tabular-nums}

    /* The stage. In a spread the book is two pages wide with leaves stacked
       on the right half; on a phone the viewport is one page wide and pans
       across the same book, so the markup is identical in both. */
    .vx-fb-stage{position:relative;margin:0 auto;width:100%;max-width:1120px;filter:drop-shadow(0 12px 18px rgb(0 0 0/.18));user-select:none;-webkit-user-select:none;touch-action:pan-y}
    .vx-fb-viewport{position:relative;width:100%;aspect-ratio:1.45/1;max-height:max(32rem,calc(100vh - 15rem));overflow:hidden;border-radius:.4rem;perspective:2600px}
    .vx-fb-book{position:absolute;inset:0;transform-style:preserve-3d;transition:transform var(--fb-turn) ease}
    .vx-fb-leaf{position:absolute;top:0;left:50%;width:50%;height:100%;transform-origin:left center;transform-style:preserve-3d;transition:transform var(--fb-turn) cubic-bezier(.4,.1,.25,1)}
    .vx-fb-leaf.is-turned{transform:rotateY(-180deg)}
    .vx-fb-face{position:absolute;inset:0;backface-visibility:hidden;-webkit-backface-visibility:hidden;background:var(--fb-paper);color:var(--fb-ink);overflow:hidden;display:flex;flex-direction:column;box-shadow:0 1px 2px rgb(0 0 0/.06)}
    .vx-fb-face.is-back{transform:rotateY(180deg)}
    /* The gutter shadow is what makes two rectangles read as a bound book. */
    .vx-fb-face.is-front::before,.vx-fb-face.is-back::before{content:"";position:absolute;top:0;bottom:0;width:28px;pointer-events:none;z-index:2}
    .vx-fb-face.is-front::before{left:0;background:linear-gradient(90deg,rgb(0 0 0/.14),transparent)}
    .vx-fb-face.is-back::before{right:0;background:linear-gradient(270deg,rgb(0 0 0/.14),transparent)}
    .vx-fb-leaf.is-moving .vx-fb-face::after{content:"";position:absolute;inset:0;pointer-events:none;animation:vx-fb-shade var(--fb-turn) ease}
    @keyframes vx-fb-shade{0%,100%{background:transparent}50%{background:rgb(0 0 0/.12)}}

    .vx-fb-inner{flex:1;min-height:0;overflow-y:auto;padding:1.6rem 1.8rem 0;scrollbar-width:thin}
    .vx-fb-foot{flex-shrink:0;display:flex;justify-content:space-between;gap:1rem;padding:.55rem 1.8rem .7rem;font-size:.68rem;color:var(--fb-muted);border-top:1px solid var(--fb-rule);margin:0 1.8rem;padding-left:0;padding-right:0}
    .vx-fb-face.is-back .vx-fb-foot{flex-direction:row-reverse}
    .vx-fb-kicker{font-size:.66rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--fb-accent)}
    .vx-fb-h{font-size:1.15rem;font-weight:700;line-height:1.3;margin-top:.25rem;color:#111827}
    .vx-fb-where{display:inline-flex;margin-top:.5rem;padding:.2rem .5rem;border-radius:.35rem;background:rgb(238 242 255);color:#3730a3;font-size:.7rem;font-weight:600}
    .vx-fb-body{margin-top:.75rem;font-size:.82rem;line-height:1.6;color:#374151}
    .vx-fb-body p+p{margin-top:.5rem}
    .vx-fb-shot{display:block;margin-top:.9rem;border:1px solid var(--fb-rule);border-radius:.4rem;background:#fff;overflow:hidden}
    .vx-fb-shot img{display:block;width:100%;height:auto;max-height:15rem;object-fit:contain;object-position:top;background:#f8fafc}
    .vx-fb-fields{margin-top:.9rem;border-top:1px solid var(--fb-rule)}
    .vx-fb-fields div{display:grid;grid-template-columns:7.5rem 1fr;gap:.6rem;padding:.4rem 0;border-bottom:1px solid var(--fb-rule);font-size:.72rem;line-height:1.45}
    .vx-fb-fields dt{font-weight:700;color:#3730a3}
    .vx-fb-fields dd{color:#4b5563}
    .vx-fb-note{margin:.9rem 0 1.2rem;padding:.55rem .7rem;border-radius:.4rem;border:1px solid #fde68a;background:#fffbeb;color:#78350f;font-size:.72rem;line-height:1.5}
    .vx-fb-rows div{padding:.6rem 0;border-bottom:1px solid var(--fb-rule)}
    .vx-fb-rows b{display:block;font-size:.8rem;color:#111827}
    .vx-fb-rows span{display:block;margin-top:.15rem;font-size:.74rem;line-height:1.5;color:#4b5563}
    .vx-fb-toc a{display:flex;align-items:baseline;gap:.5rem;padding:.45rem 0;border-bottom:1px dotted var(--fb-rule);font-size:.85rem;color:#1f2937;cursor:pointer}
    .vx-fb-toc a:hover{color:var(--fb-accent)}
    .vx-fb-toc a .dots{flex:1}
    .vx-fb-toc a small{color:var(--fb-muted);font-size:.7rem}
    .vx-fb-cover{background:linear-gradient(150deg,#0b1026 0%,#1e1b4b 55%,#312e81 100%);color:#fff;justify-content:center;padding:2.4rem}
    .vx-fb-cover .vx-fb-kicker{color:#a5b4fc}
    .vx-fb-cover h1{font-size:2rem;font-weight:800;line-height:1.1;margin-top:.6rem}
    .vx-fb-cover p{margin-top:1rem;font-size:.9rem;line-height:1.6;color:#c7d2fe;max-width:22rem}
    .vx-fb-chapter{justify-content:center;padding:2.2rem}
    .vx-fb-chapter .num{font-size:4rem;font-weight:800;line-height:1;color:var(--fb-accent);opacity:.18}
    .vx-fb-chapter ol{margin-top:1.2rem;list-style:decimal;padding-left:1.1rem;font-size:.78rem;line-height:1.7;color:#4b5563}

    /* One page at a time: the viewport is half as wide as the book and pans. */
    .vx-fb.is-single .vx-fb-viewport{aspect-ratio:auto;height:max(30rem,calc(100dvh - 16rem));max-height:none;max-width:560px;margin:0 auto}
    .vx-fb.is-single .vx-fb-book{width:200%;right:auto}
    .vx-fb.is-single .vx-fb-inner{padding:1.3rem 1.2rem 0}
    .vx-fb.is-single .vx-fb-foot{margin:0 1.2rem}
    .vx-fb.is-single .vx-fb-face.is-front::before,.vx-fb.is-single .vx-fb-face.is-back::before{width:12px}

    @media (prefers-reduced-motion:reduce){.vx-fb{--fb-turn:0s}.vx-fb-leaf.is-moving .vx-fb-face::after{animation:none}}
</style>

<div class="vx-fb space-y-4"
     x-data="{
        page: 0,
        total: {{ $total }},
        single: false,
        moving: [],
        init() {
            const mq = window.matchMedia('(max-width: 1023px)');
            const sync = () => { this.single = mq.matches; this.go(this.page); };
            // Read the address before the first go() rewrites it, so a link
            // to page 12 opens on page 12.
            const fromHash = parseInt((location.hash.match(/page-(\d+)/) || [])[1]);
            if (! isNaN(fromHash)) this.page = fromHash - 1;
            mq.addEventListener('change', sync);
            sync();
        },
        get leaves() { return Math.ceil(this.total / 2); },
        get turned() { return Math.floor((this.page + 1) / 2); },
        normalise(p) {
            p = Math.max(0, Math.min(this.total - 1, p));
            // A spread is addressed by its right-hand page.
            return this.single ? p : (p % 2 ? p + 1 : p);
        },
        go(p) {
            const before = this.turned;
            this.page = this.normalise(p);
            const after = this.turned;
            const from = Math.min(before, after), to = Math.max(before, after);
            this.moving = Array.from({ length: to - from }, (_, i) => from + i);
            clearTimeout(this._t);
            this._t = setTimeout(() => this.moving = [], 800);
            history.replaceState(null, '', '#page-' + (this.page + 1));
        },
        next() { this.go(this.single ? this.page + 1 : this.page + 2); },
        prev() { this.go(this.single ? this.page - 1 : this.page - 1 - (this.page % 2 ? 0 : 1)); },
        get atStart() { return this.page === 0; },
        get atEnd() { return this.single ? this.page >= this.total - 1 : this.turned >= this.leaves; },
        leafClass(i) { return { 'is-turned': i < this.turned, 'is-moving': this.moving.includes(i) }; },
        leafZ(i) {
            if (this.moving.includes(i)) return 500;
            return i < this.turned ? i + 1 : this.leaves - i;
        },
        // A closed book sits in the middle of the stage, not on one half of it.
        get bookShift() {
            if (this.single) return this.page % 2 === 0 ? '-50%' : '0';
            if (this.turned === 0) return '-25%';
            if (this.turned >= this.leaves) return '25%';
            return '0';
        },
        near(i) { return Math.abs(i - this.page) <= 3; },
        get label() {
            if (this.single) return 'Page ' + (this.page + 1) + ' of ' + this.total;
            const l = this.page - 1, r = this.page;
            const shown = [l, r].filter(n => n >= 0 && n < this.total).map(n => n + 1);
            return (shown.length > 1 ? 'Pages ' + shown.join('–') : 'Page ' + shown[0]) + ' of ' + this.total;
        },
        touchX: null,
        swipeEnd(e) {
            if (this.touchX === null) return;
            const dx = e.changedTouches[0].clientX - this.touchX;
            this.touchX = null;
            if (Math.abs(dx) > 45) dx < 0 ? this.next() : this.prev();
        },
     }"
     :class="{ 'is-single': single }"
     @keydown.window.arrow-right="if (! ['INPUT','SELECT','TEXTAREA'].includes($event.target.tagName)) next()"
     @keydown.window.arrow-left="if (! ['INPUT','SELECT','TEXTAREA'].includes($event.target.tagName)) prev()">

    <div class="vx-fb-bar">
        <div class="vx-fb-group">
            <a href="{{ \App\Filament\Pages\Handbook::getUrl() }}" class="vx-fb-btn">
                <x-heroicon-o-arrow-left class="h-4 w-4" /> Handbook
            </a>
            <label class="sr-only" for="vx-fb-jump">Jump to</label>
            <select id="vx-fb-jump" class="vx-fb-select" @change="go(parseInt($event.target.value)); $event.target.blur()">
                <option value="" selected disabled>Jump to…</option>
                @foreach ($chapters as $chapter)
                    <option value="{{ $chapter['page'] }}">{{ $chapter['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="vx-fb-group">
            <a href="{{ route('export.inventory-manual-pdf') }}" target="_blank" rel="noopener" class="vx-fb-btn">
                <x-heroicon-o-document-arrow-down class="h-4 w-4" /> Printable PDF
            </a>
        </div>
    </div>

    <div class="vx-fb-stage" @touchstart.passive="touchX = $event.touches[0].clientX" @touchend="swipeEnd($event)">
        <div class="vx-fb-viewport">
            <div class="vx-fb-book" :style="'transform:translateX(' + bookShift + ')'">
                @foreach (array_chunk($pages, 2) as $leaf => $faces)
                    <div class="vx-fb-leaf" :class="leafClass({{ $leaf }})" :style="'z-index:' + leafZ({{ $leaf }})" wire:key="leaf-{{ $leaf }}">
                        @foreach ($faces as $side => $p)
                            @php $index = $leaf * 2 + $side; @endphp
                            <section class="vx-fb-face {{ $side === 0 ? 'is-front' : 'is-back' }} {{ $p['kind'] === 'cover' ? 'vx-fb-cover' : '' }} {{ $p['kind'] === 'back' ? 'vx-fb-cover' : '' }}"
                                     id="vx-fb-page-{{ $index + 1 }}" data-flipbook-page="{{ $p['kind'] }}"
                                     aria-label="Page {{ $index + 1 }}"
                                     :aria-hidden="! [page - 1, page].includes({{ $index }}) || (single && page !== {{ $index }})">

                                @switch ($p['kind'])
                                    @case ('cover')
                                        <div class="vx-fb-kicker">{{ \App\Models\Setting::get('brand_name', 'VortexOps') }}</div>
                                        <h1>{{ $p['title'] }}</h1>
                                        <p>{{ $p['subtitle'] }}</p>
                                        <p style="margin-top:2.2rem;font-size:.75rem;color:#a5b4fc">Turn the page with the arrows, your arrow keys, or a swipe.</p>
                                        @break

                                    @case ('back')
                                        <div class="vx-fb-kicker">{{ \App\Models\Setting::get('brand_name', 'VortexOps') }}</div>
                                        <h1 style="font-size:1.4rem">That is the whole job.</h1>
                                        <p>Every page here is the same text as the Handbook screen and the printed PDF, so whichever you read, it says the same thing.</p>
                                        <p style="margin-top:1.6rem"><button type="button" class="vx-fb-btn is-primary" @click="go(0)">Back to the cover</button></p>
                                        @break

                                    @case ('blank')
                                        <div class="vx-fb-inner"></div>
                                        @break

                                    @case ('contents')
                                        <div class="vx-fb-inner">
                                            <div class="vx-fb-kicker">Contents</div>
                                            <h2 class="vx-fb-h">What is in this book</h2>
                                            <nav class="vx-fb-toc" style="margin-top:1rem">
                                                @foreach ($p['entries'] as $entry)
                                                    <a role="button" tabindex="0" @click="go({{ $entry['page'] }})" @keydown.enter="go({{ $entry['page'] }})">
                                                        <span>{{ $entry['label'] }}@if ($entry['steps']) <small>· {{ $entry['steps'] }} {{ \Illuminate\Support\Str::plural('step', $entry['steps']) }}</small>@endif</span>
                                                        <span class="dots"></span>
                                                        <small>{{ $entry['page'] + 1 }}</small>
                                                    </a>
                                                @endforeach
                                            </nav>
                                        </div>
                                        @break

                                    @case ('section')
                                        <div class="vx-fb-inner vx-fb-chapter" style="display:flex;flex-direction:column">
                                            <div class="num">{{ str_pad((string) $p['number'], 2, '0', STR_PAD_LEFT) }}</div>
                                            <div class="vx-fb-kicker" style="margin-top:.5rem">Section {{ $p['number'] }}</div>
                                            <h2 class="vx-fb-h" style="font-size:1.5rem">{{ $p['title'] }}</h2>
                                            <div class="vx-fb-body">{!! $p['blurb'] !!}</div>
                                            <ol>
                                                @foreach ($p['steps'] as $title)
                                                    <li>{{ $title }}</li>
                                                @endforeach
                                            </ol>
                                        </div>
                                        @break

                                    @case ('step')
                                        @php $step = $p['step']; @endphp
                                        <div class="vx-fb-inner">
                                            <div class="vx-fb-kicker">Step {{ $p['number'] }}</div>
                                            <h2 class="vx-fb-h">{{ $step['title'] }}</h2>
                                            @if (! empty($step['where']))
                                                <div class="vx-fb-where">{{ $step['where'] }}</div>
                                            @endif

                                            @foreach ($p['shots'] as $shot)
                                                @php $sizes[$shot] ??= @getimagesize(public_path(\App\Support\InventoryManual::IMAGE_DIR . '/' . $shot)) ?: [null, null]; @endphp
                                                <a href="{{ $this->imageUrl($shot) }}" target="_blank" rel="noopener" class="vx-fb-shot" title="Open the full-size screenshot">
                                                    {{-- Loaded as the page comes near, not all eighty at once. --}}
                                                    <img data-src="{{ $this->imageUrl($shot) }}" alt="{{ $step['title'] }}"
                                                         @if ($sizes[$shot][0]) width="{{ $sizes[$shot][0] }}" height="{{ $sizes[$shot][1] }}" @endif
                                                         x-effect="if (near({{ $index }}) && ! $el.src) $el.src = $el.dataset.src">
                                                </a>
                                            @endforeach

                                            <div class="vx-fb-body">
                                                @foreach ($step['body'] as $paragraph)
                                                    <p>{!! $paragraph !!}</p>
                                                @endforeach
                                            </div>

                                            @if (! empty($step['fields']))
                                                <dl class="vx-fb-fields">
                                                    @foreach ($step['fields'] as [$field, $meaning])
                                                        <div><dt>{{ $field }}</dt><dd>{!! $meaning !!}</dd></div>
                                                    @endforeach
                                                </dl>
                                            @endif

                                            @if (! empty($step['note']))
                                                <div class="vx-fb-note">{!! $step['note'] !!}</div>
                                            @else
                                                <div style="height:1.2rem"></div>
                                            @endif
                                        </div>
                                        @break

                                    @case ('troubleshooting')
                                    @case ('screens')
                                        <div class="vx-fb-inner">
                                            <div class="vx-fb-kicker">{{ $p['kind'] === 'screens' ? 'Screen index' : 'Troubleshooting' }}@if ($p['part']) · {{ $p['part'] }}@endif</div>
                                            <h2 class="vx-fb-h">{{ $p['title'] }}</h2>
                                            <div class="vx-fb-rows" style="margin-top:.6rem">
                                                @foreach ($p['rows'] as [$label, $text])
                                                    <div><b>{{ $label }}</b><span>{{ $text }}</span></div>
                                                @endforeach
                                            </div>
                                        </div>
                                        @break
                                @endswitch

                                @unless (in_array($p['kind'], ['cover', 'back'], true))
                                    <div class="vx-fb-foot">
                                        <span>{{ $p['section'] ?? $p['title'] ?? ($p['kind'] === 'contents' ? 'Contents' : '') }}</span>
                                        <span>{{ $index + 1 }}</span>
                                    </div>
                                @endunless
                            </section>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="vx-fb-bar" style="justify-content:center">
        <button type="button" class="vx-fb-btn" @click="go(0)" :disabled="atStart" x-show="! single" aria-label="Back to the cover">
            <x-heroicon-o-chevron-double-left class="h-4 w-4" />
        </button>
        <button type="button" class="vx-fb-btn" @click="prev()" :disabled="atStart">
            <x-heroicon-o-chevron-left class="h-4 w-4" /> Previous
        </button>
        <span class="vx-fb-count" x-text="label" aria-live="polite">Page 1 of {{ $total }}</span>
        <button type="button" class="vx-fb-btn is-primary" @click="next()" :disabled="atEnd">
            Next <x-heroicon-o-chevron-right class="h-4 w-4" />
        </button>
    </div>
</div>
</x-filament-panels::page>
