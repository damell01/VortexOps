<x-filament-widgets::widget>
@php
    $e = $this->estimate();
    $m = fn ($v) => ($v < 0 ? '−$' : '$').number_format(abs((float) $v), 2);
    $chips = ['estimate' => ['Estimate', 'is-est'], 'draft' => ['On pay run', 'is-run'], 'final' => ['Approved', 'is-run'], 'paid' => ['Paid', 'is-paid'], 'waiting' => ['Waiting on earnings', 'is-wait']];
@endphp
@if ($e)
<section class="vxpp">
    <div class="vxpp-hero">
        <div class="vxpp-top">
            <button type="button" class="vxpp-nav" wire:click="shift(-1)" aria-label="Earlier pay period">‹</button>
            <div class="vxpp-when">
                <small>{{ $this->back === 0 ? 'This pay period' : ($this->back === 1 ? 'Last pay period' : $this->back.' periods ago') }}</small>
                <b>{{ $e['label'] }}</b>
            </div>
            <button type="button" class="vxpp-nav" wire:click="shift(1)" @disabled($this->back === 0) aria-label="Later pay period">›</button>
        </div>
        <div class="vxpp-total">{{ $m($e['total']) }}</div>
        <div class="vxpp-sub">
            {{ $this->back === 0 ? 'Estimated pay so far' : 'Pay for this period' }} · {{ $e['shows'] }} {{ \Illuminate\Support\Str::plural('show', $e['shows']) }}
            @if ($this->back === 0 && $e['days_left'] > 0) · {{ $e['days_left'] }} {{ \Illuminate\Support\Str::plural('day', $e['days_left']) }} left @endif
        </div>
        <div class="vxpp-split">
            @if ($e['paid'] > 0)<span><i class="is-paid"></i>Paid {{ $m($e['paid']) }}</span>@endif
            @if ($e['confirmed'] > 0)<span><i class="is-run"></i>On a pay run {{ $m($e['confirmed']) }}</span>@endif
            @if ($e['estimate'] > 0)<span><i class="is-est"></i>Estimated {{ $m($e['estimate']) }}</span>@endif
            @if ($e['waiting'] > 0)<span><i class="is-wait"></i>{{ $e['waiting'] }} waiting on earnings</span>@endif
        </div>
    </div>

    @if ($e['rows'])
        <div class="vxpp-list">
            @foreach ($e['rows'] as $r)
                @php [$word, $cls] = $chips[$r['state']] ?? ['Estimate', 'is-est']; @endphp
                <div class="vxpp-row" title="{{ $r['notes'] }}">
                    <div style="min-width:0">
                        <b>{{ $r['title'] }}</b>
                        <span>{{ $r['date']?->format('D, M j') }}{{ $r['channel'] ? ' · '.$r['channel'] : '' }}</span>
                    </div>
                    <div class="vxpp-amt">
                        <b>{{ $r['amount'] === null ? '—' : $m($r['amount']) }}</b>
                        <span class="vxpp-chip {{ $cls }}">{{ $word }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="vxpp-empty">No shows in this pay period yet. Your pay shows up here as soon as a show airs.</p>
    @endif
    <p class="vxpp-foot">Estimates use your pay plan and each show's latest numbers. They can change until the pay run is approved.
        @if (\App\Filament\Pages\StreamerStatement::canAccess())<a href="{{ \App\Filament\Pages\StreamerStatement::getUrl() }}">Full statement →</a>@endif
    </p>
</section>
<style>
.vxpp{display:grid;gap:12px}
.vxpp-hero{border-radius:22px;padding:18px;color:#fff;background:linear-gradient(140deg,#7c3aed,#4338ca 65%,#2563eb);box-shadow:0 20px 40px -20px rgba(124,58,237,.6);position:relative;overflow:hidden}
.vxpp-hero::after{content:"";position:absolute;right:-60px;top:-60px;width:190px;height:190px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.22),transparent 70%);pointer-events:none}
.vxpp-top{display:flex;align-items:center;gap:10px;position:relative;z-index:1}
.vxpp-when{flex:1;text-align:center}.vxpp-when small{display:block;font-size:11.5px;font-weight:750;letter-spacing:.07em;text-transform:uppercase;opacity:.85}.vxpp-when b{font-size:15px}
.vxpp-nav{width:40px;height:40px;border-radius:12px;border:0;background:rgba(255,255,255,.16);color:#fff;font-size:22px;line-height:1;cursor:pointer}
.vxpp-nav:disabled{opacity:.35;cursor:default}
.vxpp-total{margin-top:12px;text-align:center;font-size:40px;font-weight:850;letter-spacing:-.03em;font-variant-numeric:tabular-nums}
.vxpp-sub{text-align:center;font-size:13px;opacity:.88}
.vxpp-split{display:flex;flex-wrap:wrap;justify-content:center;gap:6px 14px;margin-top:12px;font-size:12.5px;font-weight:650}
.vxpp-split span{display:inline-flex;align-items:center;gap:6px}.vxpp-split i{width:9px;height:9px;border-radius:99px;background:#fff}
.vxpp-split i.is-paid{background:#4ade80}.vxpp-split i.is-run{background:#93c5fd}.vxpp-split i.is-est{background:#fde68a}.vxpp-split i.is-wait{background:rgba(255,255,255,.5)}
.vxpp-list{display:grid;gap:8px;margin-top:10px}@media(min-width:900px){.vxpp-list{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(min-width:1400px){.vxpp-list{grid-template-columns:repeat(3,minmax(0,1fr))}}
.vxpp-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border-radius:14px;border:1px solid var(--vx-border,#e5e7eb);background:var(--vx-surface,#fff)}
.dark .vxpp-row{background:#18151f;border-color:#2c2839}
.vxpp-row b{display:block;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vxpp-row span{font-size:12px;color:var(--vx-muted,#64748b)}
.vxpp-amt{text-align:right;flex:none}.vxpp-amt b{font-variant-numeric:tabular-nums}
.vxpp-chip{display:inline-block;margin-top:3px;font-size:11px!important;font-weight:750;padding:1px 8px;border-radius:99px}
.vxpp-chip.is-est{background:#fef3c7;color:#92400e!important}.vxpp-chip.is-run{background:#dbeafe;color:#1d4ed8!important}.vxpp-chip.is-paid{background:#dcfce7;color:#15803d!important}.vxpp-chip.is-wait{background:#f1f5f9;color:#64748b!important}
.vxpp-empty{margin:0;text-align:center;color:var(--vx-muted,#64748b);font-size:13px}
.vxpp-foot{margin:0;font-size:12px;color:var(--vx-muted,#64748b)}.vxpp-foot a{font-weight:700;color:#6d28d9;margin-left:6px}
</style>
@endif
</x-filament-widgets::widget>
