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
if(\App\Support\AdminModules::isEnabled('inventory') && \App\Filament\Resources\InventoryItemResource::canAccess()) $groups['Inventory']=array_values(array_filter([
 ['Overview',\App\Filament\Pages\InventoryOverview::getUrl(panel:'admin')],
 ['Stock Status',\App\Filament\Pages\StockStatus::getUrl(panel:'admin')],
 ['All Inventory',\App\Filament\Resources\InventoryItemResource::getUrl('index')],
 \App\Filament\Pages\InventoryAge::canAccess() ? ['Inventory Age',\App\Filament\Pages\InventoryAge::getUrl(panel:'admin')] : null,
 ['Recent Activity',\App\Filament\Pages\InventoryActivity::getUrl(panel:'admin')],
 ['Inventory Health',\App\Filament\Pages\InventoryHealth::getUrl(panel:'admin')],
 \App\Filament\Pages\InventoryReport::canAccess() ? ['Reports',\App\Filament\Pages\InventoryReport::getUrl(panel:'admin')] : null,
 \App\Filament\Resources\InventoryLocationResource::canAccess() ? ['Locations',\App\Filament\Resources\InventoryLocationResource::getUrl('index')] : null,
 \App\Filament\Resources\VendorResource::canAccess() ? ['Vendors',\App\Filament\Resources\VendorResource::getUrl('index')] : null,
 ['Quick Add Stock',\App\Filament\Resources\InventoryItemResource::getUrl('quick-add')],
 ['Scan Inventory',\App\Filament\Pages\InventoryScanner::getUrl(panel:'admin')],
]));
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
 ['Notifications',\App\Filament\Pages\NotificationSettings::getUrl(panel:'admin')],
 ['Email Log',\App\Filament\Pages\EmailLog::getUrl(panel:'admin')],
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
@include('filament.components.mobile-navigation')
