@php
$user=auth()->user(); $groups=[];
// Streamers get a nav built around their own shows: My Shows is home, the
// calendar is one click away, and inventory/admin tooling stays out of the way.
// Inventory routes stay reachable (reports create items through them); only the
// links are dropped, unless Roles & Permissions explicitly grants the page.
$streamerOnly=(bool) ($user?->isStreamer() && !$user?->isAdmin() && !$user?->isOwner());
$homeUrl=\App\Filament\Pages\DashboardImproved::getUrl(panel:'admin');
$homeLabel=$streamerOnly ? 'My Dashboard' : 'Dashboard';
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
  \App\Filament\Pages\Timekeeping::canAccess() ? ['Timekeeping',\App\Filament\Pages\Timekeeping::getUrl(panel:'admin')] : null,
 ]));
}elseif(\App\Support\AdminModules::isEnabled('streams') && \App\Filament\Pages\Shows::canAccess()) $groups['Shows']=[
 ['Shows Overview',\App\Filament\Pages\Shows::getUrl(panel:'admin')],
 ...($user?->isAdmin() ? [['Channel Review',\App\Filament\Resources\ShowResource::getUrl('channel-review')]] : []),
 ...(\App\Filament\Resources\ShowResource\Pages\ReviewShowStreamers::canAccess() ? [['Stream Review',\App\Filament\Resources\ShowResource::getUrl('stream-review')]] : []),
 ...($user?->isOwner() ? [['Whatnot Sheet Check',\App\Filament\Pages\WhatnotSheetCheck::getUrl(panel:'admin')]] : []),
 ['Show Report Inbox',\App\Filament\Resources\StreamerLogResource::getUrl('index')],
];
if(\App\Support\AdminModules::isEnabled('inventory') && \App\Filament\Resources\InventoryItemResource::canAccess() && (! $streamerOnly || \App\Support\RoleAccess::grants(\App\Filament\Resources\InventoryItemResource::class))) $groups['Inventory']=array_values(array_filter([
 ['Overview',\App\Filament\Pages\InventoryOverview::getUrl(panel:'admin')],
 ['Stock Status',\App\Filament\Pages\StockStatus::getUrl(panel:'admin')],
 ['All Inventory',\App\Filament\Resources\InventoryItemResource::getUrl('index')],
 \App\Filament\Resources\PalletResource::canAccess() ? ['Receive Inventory',\App\Filament\Resources\PalletResource::getUrl('index')] : null,
 \App\Filament\Pages\InventoryGuide::canAccess() ? ['Inventory Guide',\App\Filament\Pages\InventoryGuide::getUrl(panel:'admin', parameters: ['tab'=>'tasks'])] : null,
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

if(\App\Support\AdminModules::isEnabled('payouts') && \App\Filament\Pages\PayrollOverview::canAccess()) $groups['Payroll']=[
 ['Payroll Overview',\App\Filament\Pages\PayrollOverview::getUrl(panel:'admin')],
 ['Pay Run History',\App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index')],
 ['Payment Structures',\App\Filament\Pages\PaymentStructures::getUrl(panel:'admin')],
 ['Businesses',\App\Filament\Pages\Businesses::getUrl(panel:'admin')],
 ['Payroll Simulator',\App\Filament\Pages\PayrollSimulator::getUrl(panel:'admin')],
];
// Timekeeping is one page — the clock, and for admins a dashboard that opens
// Time & Attendance's lists — so it is a plain link, not a menu.
if(! $streamerOnly && \App\Filament\Pages\Timekeeping::canAccess()) $groups['Timekeeping']=[
 ['Timekeeping',\App\Filament\Pages\Timekeeping::getUrl(panel:'admin')],
];
$directGroups = ['Timekeeping'];
// Links may carry a section heading and an icon: [label, url, section, icon].
if(\App\Support\AdminModules::isEnabled('reporting') && \App\Filament\Pages\Reports::canAccess()) $groups['Reports']=[
 ['Reports Overview',\App\Filament\Pages\ReportsOverview::getUrl(panel:'admin'),'Reports','heroicon-o-chart-bar'],
 ['Whatnot Performance',\App\Filament\Pages\Reports::getUrl(panel:'admin'),'Financial','heroicon-o-arrow-trending-up'],
 ['Whatnot Losses',\App\Filament\Pages\WhatnotLosses::getUrl(panel:'admin'),'Financial','heroicon-o-arrow-trending-down'],
 ['Business Profitability',\App\Filament\Pages\ProfitLoss::getUrl(panel:'admin'),'Financial','heroicon-o-currency-dollar'],
 ['Ledger',\App\Filament\Resources\WhatnotLedgerResource::getUrl('index'),'Financial','heroicon-o-document-text'],
 ['Streamer Analytics',\App\Filament\Pages\StreamerAnalytics::getUrl(panel:'admin'),'Operations','heroicon-o-user-group'],
 ['Inventory Reports',\App\Filament\Pages\InventoryReport::getUrl(panel:'admin'),'Operations','heroicon-o-cube'],
];
// Fulfillment admins don't get the Payroll menu, so Businesses sits under Admin for them.
if(! $user?->isAdmin() && ! $user?->isOwner() && $user?->isFulfillmentAdmin()) $groups['Admin']=[
 ['Businesses',\App\Filament\Pages\Businesses::getUrl(panel:'admin')],
];
if($user?->isAdmin() || $user?->isOwner()) $groups['Admin']=[
 ['Users',\App\Filament\Resources\UserResource::getUrl('index')],
 ['Roles & Permissions',\App\Filament\Resources\RoleResource::getUrl('index')],
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
@if(in_array($label, $directGroups ?? [], true) && count($links) === 1)
@php $here = request()->routeIs('filament.admin.pages.timekeeping', 'filament.admin.pages.time-attendance'); @endphp
<a class="vx-mega-trigger @if($here) is-here @endif" href="{{ $links[0][1] }}" @if($here) aria-current="page" @endif><span>{{ $label }}</span></a>
@continue
@endif
<div class="vx-mega-group" x-data="{open:false}" @mouseenter="open=true" @mouseleave="open=false" @focusin="open=true" @focusout="if (!$el.contains($event.relatedTarget)) open=false" @click.outside="open=false" @keydown.escape.window="open=false">
<button type="button" class="vx-mega-trigger" :aria-expanded="open" @click="open=!open"><span>{{ $label }}</span><x-filament::icon icon="heroicon-m-chevron-down"/></button>
<div class="vx-mega-menu" x-cloak x-show="open" x-transition>
@php $sectioned = collect($links)->contains(fn ($l) => isset($l[2])); $lastSection = null; @endphp
@unless($sectioned)<div class="vx-mega-menu-title">{{ $label }}</div>@endunless
@foreach($links as $link)
@php [$text,$url] = $link; $section = $link[2] ?? null; $icon = $link[3] ?? null; @endphp
@if($sectioned && $section !== $lastSection)<div @class(['vx-mega-menu-title', 'vx-mega-section' => $lastSection !== null])>{{ $section }}</div>@php $lastSection = $section; @endphp@endif
<a href="{{ $url }}" @click="open=false" @class(['has-icon' => $icon])>@if($icon)<x-filament::icon :icon="$icon" class="vx-mega-link-icon"/>@endif<span>{{ $text }}</span>@unless($icon)<x-filament::icon icon="heroicon-m-chevron-right"/>@endunless</a>
@endforeach
@if($label==='Admin' && $ownerLinks)
<details class="vx-owner-tools"><summary>Owner tools <span aria-hidden="true">⌄</span></summary><div>@foreach($ownerLinks as [$text,$url])<a href="{{ $url }}" @click="open=false">{{ $text }}</a>@endforeach</div></details>
@endif
</div></div>
@endforeach
@if($user?->canSwitchChannels())
@if(\App\Support\BusinessContext::appliesToCurrentPage() && \App\Support\BusinessContext::available()->isNotEmpty())<div class="vx-mega-channel vx-mega-business">@livewire('business-switcher')</div>@endif
<div class="vx-mega-channel">@livewire('channel-switcher')</div>
@endif
</nav>
@include('filament.components.mobile-navigation')
