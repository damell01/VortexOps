@php
$user=auth()->user(); $groups=[];
if(\App\Support\AdminModules::isEnabled('streams') && \App\Filament\Pages\Shows::canAccess()) $groups['Shows']=[
 ['Shows Overview',\App\Filament\Pages\Shows::getUrl(panel:'admin')],
 ['End of Stream',\App\Filament\Pages\EndOfStreamForm::getUrl(panel:'admin')],
 ['Team / Streamers',\App\Filament\Resources\StreamerResource::getUrl('index')],
];
if(\App\Support\AdminModules::isEnabled('inventory') && \App\Filament\Resources\InventoryItemResource::canAccess()) $groups['Inventory']=[
 ['Inventory Overview',\App\Filament\Pages\InventoryOverview::getUrl(panel:'admin')],
 ['All Inventory',\App\Filament\Resources\InventoryItemResource::getUrl('index')],
 ['Quick Add Stock',\App\Filament\Resources\InventoryItemResource::getUrl('quick-add')],
 ['Scan Inventory',\App\Filament\Pages\InventoryScanner::getUrl(panel:'admin')],
 ['Inventory Reports',\App\Filament\Pages\InventoryReport::getUrl(panel:'admin')],
];
if(\App\Support\AdminModules::isEnabled('fulfillment') && \App\Filament\Resources\FulfillmentResource::canAccess()) $groups['Fulfillment']=[
 ['Fulfillment Dashboard',\App\Filament\Resources\FulfillmentResource::getUrl('index')],
];
if(\App\Support\AdminModules::isEnabled('payouts') && \App\Filament\Pages\PayrollOverview::canAccess()) $groups['Finance']=[
 ['Payroll',\App\Filament\Pages\PayrollOverview::getUrl(panel:'admin')],
 ['Payouts',\App\Filament\Resources\PayoutResource::getUrl('index')],
 ['Pay Runs',\App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index')],
];
if(\App\Support\AdminModules::isEnabled('reporting') && \App\Filament\Pages\Reports::canAccess()) $groups['Reports']=[
 ['Reports & Analytics',\App\Filament\Pages\Reports::getUrl(panel:'admin')],
 ['Streamer Analytics',\App\Filament\Pages\StreamerAnalytics::getUrl(panel:'admin')],
 ['Inventory Reports',\App\Filament\Pages\InventoryReport::getUrl(panel:'admin')],
 ['Ledger',\App\Filament\Resources\WhatnotLedgerResource::getUrl('index')],
];
if($user?->isAdmin() || $user?->isOwner()) $groups['Admin']=[
 ['Users',\App\Filament\Resources\UserResource::getUrl('index')],
 ['Show Data Audit',\App\Filament\Pages\ShowDataAudit::getUrl(panel:'admin')],
 ['Settings',\App\Filament\Pages\AppSettings::getUrl(panel:'admin')],
];
@endphp
<nav class="vx-desktop-mega-nav" aria-label="Primary navigation">
<a class="vx-mega-home" href="{{ \App\Filament\Pages\DashboardImproved::getUrl(panel:'admin') }}"><x-filament::icon icon="heroicon-o-home"/><span>Dashboard</span></a>
@foreach($groups as $label=>$links)
<div class="vx-mega-group" x-data="{open:false}" @click.outside="open=false" @keydown.escape.window="open=false">
<button type="button" class="vx-mega-trigger" @click="open=!open"><span>{{ $label }}</span><x-filament::icon icon="heroicon-m-chevron-down"/></button>
<div class="vx-mega-menu" x-cloak x-show="open" x-transition>
<div class="vx-mega-menu-title">{{ $label }}</div>
@foreach($links as [$text,$url])<a href="{{ $url }}" @click="open=false"><span>{{ $text }}</span><x-filament::icon icon="heroicon-m-chevron-right"/></a>@endforeach
</div></div>
@endforeach
</nav>