@php($business = \App\Support\BusinessContext::current())
@if($business)
<div class="vx-business-banner" style="--vx-biz: {{ $business->color }}" role="status">
    <span class="vx-business-banner-dot" aria-hidden="true"></span>
    <span><strong>{{ $business->name }}</strong> — payroll, pay runs, shows and the dashboard show only {{ $business->label() }}'s people.</span>
    @if(\App\Filament\Pages\Businesses::canAccess())<a href="{{ \App\Filament\Pages\Businesses::getUrl() }}" class="vx-business-banner-link">Who's in it</a>@endif
</div>
@endif
@once
<style>
.vx-business-banner{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 14px;padding:10px 14px;border-radius:12px;border:1px solid color-mix(in srgb,var(--vx-biz) 35%,transparent);background:color-mix(in srgb,var(--vx-biz) 9%,#fff);color:#1f2937;font-size:13px;line-height:1.4}
.dark .vx-business-banner{background:color-mix(in srgb,var(--vx-biz) 18%,#111827);color:#e5e7eb}
.vx-business-banner strong{color:var(--vx-biz);font-weight:800}
.dark .vx-business-banner strong{color:color-mix(in srgb,var(--vx-biz) 60%,#fff)}
.vx-business-banner-dot{width:10px;height:10px;border-radius:999px;background:var(--vx-biz);flex:none}
.vx-business-banner-link{margin-left:auto;font-weight:700;color:var(--vx-biz);text-decoration:underline;text-underline-offset:2px}
.vx-desktop-mega-nav .vx-mega-business + .vx-mega-channel{margin-left:6px!important}
.vx-business-switcher[data-scoped] .vx-channel-switcher-select-wrp{border-color:var(--vx-biz)!important;background:color-mix(in srgb,var(--vx-biz) 30%,transparent)!important}
.vx-business-switcher[data-scoped] .vx-channel-switcher-select{font-weight:700}
</style>
@endonce
