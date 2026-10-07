@php
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
@endphp
<div class="vx-mobile-menu" x-data="{open:false,section:null}" x-on:vx-open-mobile-menu.window="open=true" @keydown.escape.window="open=false" x-cloak>
<button type="button" class="vx-mobile-menu-trigger" @click="open=true" :aria-expanded="open" aria-controls="vx-mobile-navigation" aria-label="Open navigation"><x-filament::icon icon="heroicon-o-bars-3"/></button>
<template x-teleport="body"><div x-show="open" x-cloak class="vx-mobile-menu-overlay"><div class="vx-mobile-menu-backdrop" x-show="open" x-transition.opacity @click="open=false"></div>
<aside id="vx-mobile-navigation" role="dialog" aria-modal="true" aria-label="Main navigation" x-trap.inert.noscroll="open" class="vx-mobile-menu-sheet" x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" @keydown.escape.window="open=false">
<div class="vx-mobile-menu-head">@if($navLogo)<img src="{{ $navLogo }}" alt="Vortex Ops">@else<span>VortexOps</span>@endif<button @click="open=false" aria-label="Close navigation"><x-filament::icon icon="heroicon-o-x-mark"/></button></div>
<a class="vx-mobile-menu-home" href="{{ \App\Filament\Pages\DashboardImproved::getUrl(panel:'admin') }}" wire:navigate @click="open=false"><x-filament::icon icon="heroicon-o-home"/><span>Dashboard</span></a>
@foreach($groups as $label=>$links)
<div class="vx-mobile-menu-group"><button type="button" @click="section=section==={{ $loop->index }}?null:{{ $loop->index }}"><span>{{ $label }}</span><x-filament::icon icon="heroicon-m-chevron-down" x-bind:class="section==={{ $loop->index }}&&'rotate-180'"/></button>
<div class="vx-mobile-submenu" x-show="section==={{ $loop->index }}" x-collapse>@foreach($links as [$text,$url])<a href="{{ $url }}" wire:navigate @click="open=false"><span>{{ $text }}</span><x-filament::icon icon="heroicon-m-chevron-right"/></a>@endforeach</div></div>
@endforeach
@if($user?->canSwitchChannels())<div class="vx-mobile-channel">@livewire('channel-switcher')</div>@endif
</aside></div></template></div>
