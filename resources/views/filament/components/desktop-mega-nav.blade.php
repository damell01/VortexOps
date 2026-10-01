@php
$user=auth()->user(); $groups=[];
$channel=\App\Support\ChannelContext::current();
try { $globalLogo=\App\Models\Setting::get('logo_path'); } catch (\Throwable) { $globalLogo=null; }
$navLogo=null;
if($channel?->logo_path && file_exists(storage_path('app/public/'.$channel->logo_path))) $navLogo=asset('storage/'.$channel->logo_path);
elseif($globalLogo && file_exists(storage_path('app/public/'.$globalLogo))) $navLogo=asset('storage/'.$globalLogo);
if(\App\Support\AdminModules::isEnabled('streams') && \App\Filament\Pages\Shows::canAccess()) $groups['Shows']=[
 ['Shows Overview',\App\Filament\Pages\Shows::getUrl(panel:'admin')],
];
if(\App\Support\AdminModules::isEnabled('inventory') && \App\Filament\Resources\InventoryItemResource::canAccess()) $groups['Inventory']=[
 ['Inventory Overview',\App\Filament\Pages\InventoryOverview::getUrl(panel:'admin')],
 ['All Inventory',\App\Filament\Resources\InventoryItemResource::getUrl('index')],
 ['Quick Add Stock',\App\Filament\Resources\InventoryItemResource::getUrl('quick-add')],
 ['Scan Inventory',\App\Filament\Pages\InventoryScanner::getUrl(panel:'admin')],
 ['Inventory Reports',\App\Filament\Pages\InventoryReport::getUrl(panel:'admin')],
];
$groups['Fulfillment']=[
 ['Coming Soon','#'],
];
if(\App\Support\AdminModules::isEnabled('payouts') && \App\Filament\Pages\PayrollOverview::canAccess()) $groups['Finance']=[
 ['Payroll Overview',\App\Filament\Pages\PayrollOverview::getUrl(panel:'admin')],
 ['Pay Run History',\App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index')],
 ['Payment Structures',\App\Filament\Pages\PaymentStructures::getUrl(panel:'admin')],
 ['Payroll Simulator',\App\Filament\Pages\PayrollSimulator::getUrl(panel:'admin')],
];
if(\App\Support\AdminModules::isEnabled('reporting') && \App\Filament\Pages\Reports::canAccess()) $groups['Reports']=[
 ['Reports & Analytics',\App\Filament\Pages\Reports::getUrl(panel:'admin')],
 ['Streamer Analytics',\App\Filament\Pages\StreamerAnalytics::getUrl(panel:'admin')],
 ['Inventory Reports',\App\Filament\Pages\InventoryReport::getUrl(panel:'admin')],
 ['Ledger',\App\Filament\Resources\WhatnotLedgerResource::getUrl('index')],
];
if($user?->isAdmin() || $user?->isOwner()) $groups['Admin']=[
 ['Users',\App\Filament\Resources\UserResource::getUrl('index')],
 ...($user?->isOwner() ? [
   ['Show Data Audit',\App\Filament\Pages\ShowDataAudit::getUrl(panel:'admin')],
   ['Show Ingestion Logs',\App\Filament\Resources\ShowIngestionLogResource::getUrl('index')],
   ['Activity Logs',\App\Filament\Resources\ActivityLogResource::getUrl('index')],
   ['Inventory Logs',\App\Filament\Resources\InventoryMovementResource::getUrl('index')],
 ] : []),
 ['Settings',\App\Filament\Pages\AppSettings::getUrl(panel:'admin')],
];
@endphp
<nav class="vx-desktop-mega-nav" aria-label="Primary navigation">
@if($navLogo)<a class="vx-mega-brand" href="{{ \App\Filament\Pages\DashboardImproved::getUrl(panel:'admin') }}" aria-label="Vortex Ops dashboard"><img src="{{ $navLogo }}" alt="Vortex Ops"></a>@endif
<a class="vx-mega-home" href="{{ \App\Filament\Pages\DashboardImproved::getUrl(panel:'admin') }}"><x-filament::icon icon="heroicon-o-home"/><span>Dashboard</span></a>
@foreach($groups as $label=>$links)
<div class="vx-mega-group" x-data="{open:false}" @mouseenter="open=true" @mouseleave="open=false" @focusin="open=true" @focusout="if (!$el.contains($event.relatedTarget)) open=false" @click.outside="open=false" @keydown.escape.window="open=false">
<button type="button" class="vx-mega-trigger" @click="open=!open"><span>{{ $label }}</span><x-filament::icon icon="heroicon-m-chevron-down"/></button>
<div class="vx-mega-menu" x-cloak x-show="open" x-transition>
<div class="vx-mega-menu-title">{{ $label }}</div>
@foreach($links as [$text,$url])<a href="{{ $url }}" @click="open=false"><span>{{ $text }}</span><x-filament::icon icon="heroicon-m-chevron-right"/></a>@endforeach
</div></div>
@endforeach
@if($user?->canSwitchChannels())
<div class="vx-mega-channel">@livewire('channel-switcher')</div>
@endif
</nav>
<div class="vx-mobile-menu" x-data="{open:false,section:null}" x-on:vx-open-mobile-menu.window="open=true" x-cloak>
<button type="button" class="vx-mobile-menu-trigger" @click="open=true" aria-label="Open navigation"><x-filament::icon icon="heroicon-o-bars-3"/></button>
<div class="vx-mobile-menu-backdrop" x-show="open" x-transition.opacity @click="open=false"></div>
<aside class="vx-mobile-menu-sheet" x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" @keydown.escape.window="open=false">
<div class="vx-mobile-menu-head">@if($navLogo)<img src="{{ $navLogo }}" alt="Vortex Ops">@else<span>VortexOps</span>@endif<button @click="open=false" aria-label="Close navigation"><x-filament::icon icon="heroicon-o-x-mark"/></button></div>
<a class="vx-mobile-menu-home" href="{{ \App\Filament\Pages\DashboardImproved::getUrl(panel:'admin') }}" wire:navigate @click="open=false"><x-filament::icon icon="heroicon-o-home"/><span>Dashboard</span></a>
@foreach($groups as $label=>$links)
<div class="vx-mobile-menu-group"><button type="button" @click="section=section==='{{ $label }}'?null:'{{ $label }}'"><span>{{ $label }}</span><x-filament::icon icon="heroicon-m-chevron-down" x-bind:class="section==='{{ $label }}'&&'rotate-180'"/></button>
<div class="vx-mobile-submenu" x-show="section==='{{ $label }}'" x-collapse>@foreach($links as [$text,$url])<a href="{{ $url }}" wire:navigate @click="open=false"><span>{{ $text }}</span><x-filament::icon icon="heroicon-m-chevron-right"/></a>@endforeach</div></div>
@endforeach
@if($user?->canSwitchChannels())<div class="vx-mobile-channel">@livewire('channel-switcher')</div>@endif
</aside></div>