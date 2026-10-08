@php
    $user = auth()->user();
    // Dev uses Filament's registered, permission-filtered navigation. Production
    // supplies the same groups used by its desktop navigation.
    if (! isset($groups)) {
        $groups = [];
        foreach (filament()->getNavigation() as $group) {
            $links = [];
            foreach ($group->getItems() as $item) {
                if ($item->getUrl()) $links[] = [$item->getLabel(), $item->getUrl()];
                foreach ($item->getChildItems() as $child) {
                    if ($child->getUrl()) $links[] = [$child->getLabel(), $child->getUrl()];
                }
            }
            if ($links) $groups[$group->getLabel() ?: 'Navigation'] = $links;
        }
    }
    $navLogo = $navLogo ?? null;
    $homeUrl = $homeUrl ?? \App\Filament\Pages\DashboardImproved::getUrl(panel: 'admin');
    $homeLabel = $homeLabel ?? 'Dashboard';
    $icons = ['Shows' => 'heroicon-o-tv', 'Inventory' => 'heroicon-o-cube', 'Fulfillment' => 'heroicon-o-truck', 'Finance' => 'heroicon-o-wallet', 'Payroll' => 'heroicon-o-wallet', 'Reports' => 'heroicon-o-chart-bar', 'Admin' => 'heroicon-o-cog-6-tooth', 'Settings' => 'heroicon-o-cog-6-tooth'];
    // The section holding the page you are on opens by itself, with that page marked.
    $here = rtrim(url()->current(), '/');
    $isHere = fn (string $url) => $url !== '#' && rtrim(strtok($url, '?'), '/') === $here;
    $openIndex = null;
    foreach (array_values($groups) as $i => $links) {
        foreach ($links as [, $url]) if ($isHere($url)) { $openIndex = $i; break 2; }
    }
    $role = $user?->isOwner() ? 'Owner' : ($user?->isAdmin() ? 'Admin' : ucwords(str_replace('_', ' ', (string) ($user?->getRoleNames()->first() ?? 'Member'))));
    $initial = mb_strtoupper(mb_substr((string) ($user?->name ?? '?'), 0, 1));
@endphp
<div class="vx-mobile-menu" x-data="{open:false,section:@js($openIndex)}" x-on:vx-open-mobile-menu.window="open=true" @keydown.escape.window="open=false" x-cloak>
<button type="button" class="vx-mobile-menu-trigger" @click="open=true" :aria-expanded="open" aria-controls="vx-mobile-navigation" aria-label="Open navigation"><x-filament::icon icon="heroicon-o-bars-3"/></button>
<template x-teleport="body"><div x-show="open" x-cloak class="vx-mobile-menu-overlay"><div class="vx-mobile-menu-backdrop" x-show="open" x-transition.opacity @click="open=false"></div>
<aside x-trap.inert.noscroll="open" tabindex="-1" role="dialog" aria-modal="true" aria-label="Main navigation" id="vx-mobile-navigation" class="vx-mobile-menu-sheet" x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" @keydown.escape.window="open=false">
<div class="vx-mobile-menu-head">@if($navLogo)<img src="{{ $navLogo }}" alt="Vortex Ops">@else<span>VortexOps</span>@endif<button @click="open=false" aria-label="Close navigation"><x-filament::icon icon="heroicon-o-x-mark"/></button></div>
@if($user)
<div class="vx-mm-user"><span class="vx-mm-avatar">{{ $initial }}</span><span class="min-w-0"><span class="vx-mm-name">{{ $user->name }}</span><span class="vx-mm-role">{{ $role }}</span></span></div>
@endif
<a class="vx-mobile-menu-home @if($isHere($homeUrl)) is-here @endif" href="{{ $homeUrl }}" wire:navigate @click="open=false"><x-filament::icon icon="heroicon-o-home"/><span>{{ $homeLabel }}</span></a>
@foreach($groups as $label=>$links)
<div class="vx-mobile-menu-group"><button type="button" :aria-expanded="section==={{ $loop->index }}" @click="section=section==={{ $loop->index }}?null:{{ $loop->index }}" :class="section==={{ $loop->index }} && 'is-open'"><span class="vx-mm-gl"><x-filament::icon :icon="$icons[$label] ?? 'heroicon-o-squares-2x2'"/>{{ $label }}</span><x-filament::icon icon="heroicon-m-chevron-down" x-bind:class="section==={{ $loop->index }}&&'rotate-180'"/></button>
<div class="vx-mobile-submenu" x-show="section==={{ $loop->index }}" x-collapse>@foreach($links as [$text,$url])<a href="{{ $url }}" wire:navigate @click="open=false" @class(['is-here' => $isHere($url)]) @if($isHere($url)) aria-current="page" @endif><span>{{ $text }}</span><x-filament::icon icon="heroicon-m-chevron-right"/></a>@endforeach</div></div>
@endforeach
<a class="vx-mobile-menu-home" href="{{ \App\Filament\Pages\MyNotifications::getUrl(panel:'admin') }}" wire:navigate @click="open=false"><x-filament::icon icon="heroicon-o-bell"/><span>My notifications</span></a>
@if($user?->canSwitchChannels())<div class="vx-mobile-channel">@livewire('channel-switcher')</div>@endif
</aside></div></template></div>
<style>
.vx-mm-user{display:flex;align-items:center;gap:10px;margin:2px 0 12px;padding:10px 12px;border-radius:14px;background:#f5f3ff}
.dark .vx-mm-user{background:#1e1b3a}
.vx-mm-avatar{display:grid;place-items:center;width:38px;height:38px;border-radius:999px;background:var(--primary-600,#7c3aed);color:#fff;font-weight:800;flex:none}
.vx-mm-name{display:block;font-size:.92rem;font-weight:800;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.vx-mm-role{display:inline-block;margin-top:3px;padding:1px 8px;border-radius:999px;background:#ede9fe;color:#6d28d9;font-size:.66rem;font-weight:800}
.vx-mm-gl{display:inline-flex;align-items:center;gap:10px}
.vx-mm-gl svg{width:18px;height:18px}
.vx-mobile-menu-group>button.is-open{color:var(--primary-600,#7c3aed)}
.vx-mobile-submenu a.is-here{background:#f5f3ff;color:#6d28d9;font-weight:800;border-radius:10px}
.dark .vx-mobile-submenu a.is-here{background:#24183f;color:#ddd6fe}
.vx-mobile-menu-home.is-here{color:#6d28d9}
</style>
