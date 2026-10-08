@php
$user=auth()->user(); $groups=[];
// Streamers get a nav built around their own shows: My Shows is home, the
// calendar is one click away, and inventory/admin tooling stays out of the way.
// Inventory routes stay reachable (reports create items through them); only the
// links are dropped, unless Roles & Permissions explicitly grants the page.
$streamerOnly=(bool) $user?->isStreamer();
$homeUrl=$streamerOnly ? \App\Filament\Pages\StreamerShows::getUrl(panel:'admin') : \App\Filament\Pages\DashboardImproved::getUrl(panel:'admin');
$homeLabel=$streamerOnly ? 'My Shows' : 'Dashboard';
$channel=\App\Support\ChannelContext::current();
try { $globalLogo=\App\Models\Setting::get('logo_path'); } catch (\Throwable) { $globalLogo=null; }
$navLogo=null;
if($channel?->logo_path && file_exists(storage_path('app/public/'.$channel->logo_path))) $navLogo=asset('storage/'.$channel->logo_path);
elseif($globalLogo && file_exists(storage_path('app/public/'.$globalLogo))) $navLogo=asset('storage/'.$globalLogo);
if($streamerOnly){
 if(\App\Filament\Pages\Shows::canAccess()) $groups['Shows']=array_values(array_filter([
  ['My Shows',\App\Filament\Pages\StreamerShows::getUrl(panel:'admin')],
  ['Show Calendar',\App\Filament\Pages\Shows::getUrl(panel:'admin')],
  \App\Filament\Pages\EndOfStreamForm::canAccess() ? ['Log a Show',\App\Filament\Pages\EndOfStreamForm::getUrl(panel:'admin')] : null,
 ]));
}elseif(\App\Support\AdminModules::isEnabled('streams') && \App\Filament\Pages\Shows::canAccess()) $groups['Shows']=[
 ['Shows Overview',\App\Filament\Pages\Shows::getUrl(panel:'admin')],
 ['Show Report Inbox',\App\Filament\Resources\StreamerLogResource::getUrl('index')],
];
if(\App\Support\AdminModules::isEnabled('inventory') && \App\Filament\Resources\InventoryItemResource::canAccess() && (! $streamerOnly || \App\Support\RoleAccess::grants(\App\Filament\Resources\InventoryItemResource::class))) $groups['Inventory']=array_values(array_filter([
 ['Overview',\App\Filament\Pages\InventoryOverview::getUrl(panel:'admin')],
 ['Stock Status',\App\Filament\Pages\StockStatus::getUrl(panel:'admin')],
 ['All Inventory',\App\Filament\Resources\InventoryItemResource::getUrl('index')],
 \App\Filament\Pages\InventoryAge::canAccess() ? ['Inventory Age',\App\Filament\Pages\InventoryAge::getUrl(panel:'admin')] : null,
 \App\Filament\Resources\InventoryMovementResource::canAccess() ? ['Inventory Log',\App\Filament\Resources\InventoryMovementResource::getUrl('index')] : null,
 ['Recent Activity',\App\Filament\Pages\InventoryActivity::getUrl(panel:'admin')],
 ['Inventory Health',\App\Filament\Pages\InventoryHealth::getUrl(panel:'admin')],
 \App\Filament\Pages\InventoryReport::canAccess() ? ['Reports',\App\Filament\Pages\InventoryReport::getUrl(panel:'admin')] : null,
 \App\Filament\Resources\InventoryLocationResource::canAccess() ? ['Locations',\App\Filament\Resources\InventoryLocationResource::getUrl('index')] : null,
 \App\Filament\Resources\VendorResource::canAccess() ? ['Vendors',\App\Filament\Resources\VendorResource::getUrl('index')] : null,
 ['Quick Add Stock',\App\Filament\Resources\InventoryItemResource::getUrl('quick-add')],
 ['Scan Inventory',\App\Filament\Pages\InventoryScanner::getUrl(panel:'admin')],
]));
if(\App\Filament\Pages\FulfillmentPreview::canAccess()) {
    $groups['Shows'] ??= [];
    $groups['Shows'][] = ['Fulfillment Preview', \App\Filament\Pages\FulfillmentPreview::getUrl(panel:'admin')];
}
if(\App\Support\AdminModules::isEnabled('payouts') && \App\Filament\Pages\PayrollOverview::canAccess()) $groups['Payroll']=[
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
   ['Import Status',\App\Filament\Pages\ImportStatus::getUrl(panel:'admin')],
   ['Show Data Audit',\App\Filament\Pages\ShowDataAudit::getUrl(panel:'admin')],
   ['Show Ingestion Logs',\App\Filament\Resources\ShowIngestionLogResource::getUrl('index')],
 ] : []),
 ['Activity Logs',\App\Filament\Resources\ActivityLogResource::getUrl('index')],
 ['Notifications',\App\Filament\Pages\NotificationSettings::getUrl(panel:'admin')],
 ['Email Log',\App\Filament\Pages\EmailLog::getUrl(panel:'admin')],
 ['Settings',\App\Filament\Pages\AppSettings::getUrl(panel:'admin')],
];

// The route registry is the source of truth for each link's access.
foreach ($groups as $label => $links) {
    $groups[$label] = array_values(array_filter($links, function ($link) use ($user) {
        if ($link[1] === '#') return true;
        try {
            $request = \Illuminate\Http\Request::create($link[1]);
            $route = app('router')->getRoutes()->match($request);
            $class = $route->getAction('controller');
            $class = is_string($class) ? explode('@', $class)[0] : null;
            if ($class && method_exists($class, 'canAccess')) {
                return $class::canAccess() && ! \App\Support\NavVisibility::isHiddenForUser($class, $user);
            }
            return true;
        } catch (\Throwable) { return false; }
    }));
    if ($groups[$label] === []) unset($groups[$label]);
}
$ownerLabels = ['Import Status','Show Data Audit','Show Ingestion Logs','Email Log'];
$ownerLinks = $user?->isOwner() ? array_values(array_filter($groups['Admin'] ?? [], fn ($link) => in_array($link[0], $ownerLabels, true))) : [];
if (isset($groups['Admin'])) $groups['Admin'] = array_values(array_filter($groups['Admin'], fn ($link) => ! in_array($link[0], $ownerLabels, true)));
@endphp
<nav class="vx-desktop-mega-nav" aria-label="Primary navigation">
@if($navLogo)<a class="vx-mega-brand" href="{{ $homeUrl }}" aria-label="Vortex Ops dashboard"><img src="{{ $navLogo }}" alt="Vortex Ops"></a>@endif
<a class="vx-mega-home" href="{{ $homeUrl }}"><x-filament::icon icon="heroicon-o-home"/><span>{{ $homeLabel }}</span></a>
@foreach($groups as $label=>$links)
<div class="vx-mega-group" x-data="{open:false}" @mouseenter="open=true" @mouseleave="open=false" @focusin="open=true" @focusout="if (!$el.contains($event.relatedTarget)) open=false" @click.outside="open=false" @keydown.escape.window="open=false">
<button type="button" class="vx-mega-trigger" :aria-expanded="open" @click="open=!open"><span>{{ $label }}</span><x-filament::icon icon="heroicon-m-chevron-down"/></button>
<div class="vx-mega-menu" x-cloak x-show="open" x-transition>
<div class="vx-mega-menu-title">{{ $label }}</div>
@foreach($links as [$text,$url])<a href="{{ $url }}" @click="open=false"><span>{{ $text }}</span><x-filament::icon icon="heroicon-m-chevron-right"/></a>@endforeach
@if($label==='Admin' && $ownerLinks)
<details class="vx-owner-tools"><summary>Owner tools <span aria-hidden="true">⌄</span></summary><div>@foreach($ownerLinks as [$text,$url])<a href="{{ $url }}" @click="open=false">{{ $text }}</a>@endforeach</div></details>
@endif
</div></div>
@endforeach
@if($user?->canSwitchChannels())
<div class="vx-mega-channel">@livewire('channel-switcher')</div>
@endif
</nav>
@include('filament.components.mobile-navigation')
