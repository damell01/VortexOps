@php
$user=auth()->user(); $groups=[];
// Streamers get a nav built around their own shows: My Shows is home, the
// calendar is one click away, and inventory/admin tooling stays out of the way.
// Inventory routes stay reachable (reports create items through them); only the
// links are dropped, unless Roles & Permissions explicitly grants the page.
$assistantOnly=(bool) $user?->isStreamerAssistant();
$streamerOnly=(bool) (!$assistantOnly && $user?->isStreamer() && !$user?->isAdmin() && !$user?->isOwner());
$homeUrl=\App\Filament\Pages\DashboardImproved::getUrl(panel:'admin');
$homeLabel=$streamerOnly ? 'My Dashboard' : 'Dashboard';
$channel=\App\Support\ChannelContext::current();
try { $globalLogo=\App\Models\Setting::get('logo_path'); } catch (\Throwable) { $globalLogo=null; }
$navLogo=null;
if($channel?->logo_path && file_exists(storage_path('app/public/'.$channel->logo_path))) $navLogo=asset('storage/'.$channel->logo_path);
elseif($globalLogo && file_exists(storage_path('app/public/'.$globalLogo))) $navLogo=asset('storage/'.$globalLogo);
// Restore the built-in brand mark when no channel/global logo is configured.
if(!$navLogo && file_exists(public_path('images/vb-logo-sidebar.svg'))) $navLogo=asset('images/vb-logo-sidebar.svg');
if($assistantOnly){
 $groups=[];
}elseif($streamerOnly){
 if(\App\Filament\Pages\Shows::canAccess()) $groups['Shows']=array_values(array_filter([
  ['Show Calendar',\App\Filament\Pages\Shows::getUrl(panel:'admin')],
  \App\Filament\Pages\ShowLeaderboard::canAccess() ? ['Show Leaderboard',\App\Filament\Pages\ShowLeaderboard::getUrl(panel:'admin')] : null,
  \App\Filament\Pages\EndOfStreamForm::canAccess() ? ['Log a Show',\App\Filament\Pages\EndOfStreamForm::getUrl(panel:'admin')] : null,
  \App\Filament\Pages\Timekeeping::canAccess() ? ['Timekeeping',\App\Filament\Pages\Timekeeping::getUrl(panel:'admin')] : null,
  \App\Filament\Pages\MyLedgerCharges::canAccess() ? ['My Charges',\App\Filament\Pages\MyLedgerCharges::getUrl(panel:'admin')] : null,
 ]));
}elseif(\App\Support\AdminModules::isEnabled('streams') && \App\Filament\Pages\Shows::canAccess()) $groups['Shows']=[
 ['Shows Overview',\App\Filament\Pages\Shows::getUrl(panel:'admin')],
 ...(\App\Filament\Pages\ShowLeaderboard::canAccess() ? [['Show Leaderboard',\App\Filament\Pages\ShowLeaderboard::getUrl(panel:'admin')]] : []),
 ...($user?->isAdmin() ? [['Channel Review',\App\Filament\Resources\ShowResource::getUrl('channel-review')]] : []),
 ...(\App\Filament\Resources\ShowResource\Pages\ReviewShowStreamers::canAccess() ? [['Stream Review',\App\Filament\Resources\ShowResource::getUrl('stream-review')]] : []),
 ...($user?->isOwner() ? [['Whatnot Sheet Check',\App\Filament\Pages\WhatnotSheetCheck::getUrl(panel:'admin')]] : []),
 ['Show Report Inbox',\App\Filament\Resources\StreamerLogResource::getUrl('index')],
];
// The Fulfillment Center for admins and the owner (fulfillment-only roles get it below).
if(!$assistantOnly && !$streamerOnly && ($user?->isAdmin() || $user?->isOwner()) && \App\Support\AdminModules::isEnabled('fulfillment') && \App\Filament\Pages\FulfillmentHome::canAccess()) $groups['Fulfillment']=\App\Support\FulfillmentNav::links();
if(\App\Support\AdminModules::isEnabled('inventory') && \App\Filament\Resources\InventoryItemResource::canAccess() && (! $streamerOnly || \App\Support\RoleAccess::grants(\App\Filament\Resources\InventoryItemResource::class))) $groups['Inventory']=array_values(array_filter([
 ['Overview',\App\Filament\Pages\InventoryOverview::getUrl(panel:'admin'),'Inventory'],
 ['All Inventory',\App\Filament\Resources\InventoryItemResource::getUrl('index'),'Inventory'],
 \App\Filament\Pages\InventorySearch::canAccess() ? ['Inventory Search',\App\Filament\Pages\InventorySearch::getUrl(panel:'admin'),'Inventory'] : null,
 \App\Filament\Pages\InventoryScanner::canAccess() ? ['Scan Inventory',\App\Filament\Pages\InventoryScanner::getUrl(panel:'admin'),'Stock operations'] : null,
 \App\Filament\Pages\QuickAddStock::canAccess() ? ['Quick Add Stock',\App\Filament\Pages\QuickAddStock::getUrl(panel:'admin'),'Stock operations'] : null,
 \App\Filament\Resources\InventoryItemResource::canCreate() && \App\Support\StockGate::canChangeAny() ? ['Add Item',\App\Filament\Resources\InventoryItemResource::getUrl('create'),'Stock operations'] : null,
 \App\Filament\Pages\StockTransfer::canAccess() ? ['Transfer Stock',\App\Filament\Pages\StockTransfer::getUrl(panel:'admin'),'Stock operations'] : null,
 \App\Filament\Resources\PalletResource::canAccess() ? ['Receive Inventory',\App\Filament\Resources\PalletResource::getUrl('index'),'Receiving'] : null,
 \App\Filament\Pages\ImportInventorySheet::canAccess() ? ['Import a Sheet',\App\Filament\Pages\ImportInventorySheet::getUrl(panel:'admin'),'Receiving'] : null,
 \App\Filament\Pages\InventoryActivity::canAccess() ? ['Inventory History',\App\Filament\Pages\InventoryActivity::getUrl(panel:'admin'),'History & setup'] : null,
 \App\Filament\Resources\InventoryLocationResource::canAccess() ? ['Locations',\App\Filament\Resources\InventoryLocationResource::getUrl('index'),'History & setup'] : null,
 \App\Filament\Resources\VendorResource::canAccess() ? ['Vendors',\App\Filament\Resources\VendorResource::getUrl('index'),'History & setup'] : null,
]));

if(\App\Support\AdminModules::isEnabled('payouts') && \App\Filament\Pages\PayrollOverview::canAccess()) $groups['Payroll']=[
 ['Payroll Overview',\App\Filament\Pages\PayrollOverview::getUrl(panel:'admin')],
 ['Pay Run History',\App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index')],
 ...(($user?->isAdmin() || $user?->isOwner()) && \App\Support\AdminModules::isEnabled('payouts') ? [['Streamer Loans',\App\Filament\Resources\StreamerLoanResource::getUrl('index')]] : []),
 ['Payment Structures',\App\Filament\Pages\PaymentStructures::getUrl(panel:'admin')],
 ['Businesses',\App\Filament\Pages\Businesses::getUrl(panel:'admin')],
 ['Payroll Simulator',\App\Filament\Pages\PayrollSimulator::getUrl(panel:'admin')],
 ...(\App\Filament\Pages\PayChangedSincePaid::canAccess() ? [[\App\Support\LedgerNav::changedLabel(),\App\Filament\Pages\PayChangedSincePaid::getUrl(panel:'admin')]] : []),
 ...(\App\Filament\Resources\WhatnotLedgerResource::canAccess() ? [['Ledger Charges',\App\Filament\Resources\WhatnotLedgerResource::getUrl('charges')]] : []),
];
// Timekeeping is one page — the clock, and for admins a dashboard that opens
// Time & Attendance's lists — so it is a plain link, not a menu.
if(! $streamerOnly && \App\Filament\Pages\Timekeeping::canAccess()) $groups['Timekeeping']=[
 ['Timekeeping',\App\Filament\Pages\Timekeeping::getUrl(panel:'admin')],
];
if($assistantOnly && \App\Filament\Pages\MyPay::canAccess()) $groups['My Pay']=[['My Pay',\App\Filament\Pages\MyPay::getUrl(panel:'admin')]];
$directGroups = ['Timekeeping', 'My Pay'];
// Links may carry a section heading and an icon: [label, url, section, icon].
if(\App\Support\AdminModules::isEnabled('reporting') && \App\Filament\Pages\Reports::canAccess()) $groups['Reports']=[
 ['Reports Overview',\App\Filament\Pages\ReportsOverview::getUrl(panel:'admin'),'Reports','heroicon-o-chart-bar'],
 ['Whatnot Performance',\App\Filament\Pages\Reports::getUrl(panel:'admin'),'Financial','heroicon-o-arrow-trending-up'],
 ['Whatnot Losses',\App\Filament\Pages\WhatnotLosses::getUrl(panel:'admin'),'Financial','heroicon-o-arrow-trending-down'],
 ...($user?->canSeeBusinessFinancials() ? [['Business Profitability',\App\Filament\Pages\ProfitLoss::getUrl(panel:'admin'),'Financial','heroicon-o-currency-dollar']] : []),
 ...(\App\Filament\Pages\ShowPerformance::canAccess() ? [['Show Performance',\App\Filament\Pages\ShowPerformance::getUrl(panel:'admin'),'Financial','heroicon-o-fire']] : []),
 ...\App\Support\LedgerNav::links(),
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
   ['Owner Tools',\App\Filament\Pages\OwnerTools::getUrl(panel:'admin')],
   ['Messages & Announcements',\App\Filament\Pages\Communications::getUrl(panel:'admin')],
   ['Import Status',\App\Filament\Pages\ImportStatus::getUrl(panel:'admin')],
   ['Show Data Audit',\App\Filament\Pages\ShowDataAudit::getUrl(panel:'admin')],
   ['Show Ingestion Logs',\App\Filament\Resources\ShowIngestionLogResource::getUrl('index')],
   ['Whatnot Scraper',\App\Filament\Pages\WhatnotSyncPage::getUrl(panel:'admin')],
   ... (\App\Filament\Pages\InventoryPopupSettings::canAccess() ? [['Streamer Login Notifications',\App\Filament\Pages\InventoryPopupSettings::getUrl(panel:'admin')]] : []),
   ... (\App\Filament\Pages\WhatnotApiDashboard::canAccess() ? [['Whatnot API Control Center',\App\Filament\Pages\WhatnotApiDashboard::getUrl(panel:'admin')]] : []), ...(\App\Filament\Pages\WhatnotApiChanges::canAccess() ? [['Whatnot API Changes',\App\Filament\Pages\WhatnotApiChanges::getUrl(panel:'admin')]] : []),
 ] : []),
 ['Activity Logs',\App\Filament\Resources\ActivityLogResource::getUrl('index')],
 ['Notifications',\App\Filament\Pages\NotificationSettings::getUrl(panel:'admin')],
 ['Email Log',\App\Filament\Pages\EmailLog::getUrl(panel:'admin')],
 ['Settings',\App\Filament\Pages\AppSettings::getUrl(panel:'admin')],
];

// Dedicated fulfillment navigation for fulfillment-only roles.
// The route registry below still enforces canAccess and NavVisibility.
$fulfillmentOnly=(bool) (!$assistantOnly && !$streamerOnly && !$user?->isOwner() && !$user?->hasAnyRole(['admin', 'super_admin'])
    && ($user?->isFulfillment() || $user?->isFulfillmentAdmin()));
if ($fulfillmentOnly) {
    $homeLabel = $user?->isFulfillmentAdmin() ? 'Command Center' : 'My Workspace';
    // Fulfillment admins only need the All Inventory list, not the inventory tools menu.
    // Regular fulfillment members keep their existing scoped navigation.
    if ($user?->isFulfillmentAdmin()) {
        $groups = [];
        if (\App\Support\AdminModules::isEnabled('streams') && \App\Filament\Pages\Shows::canAccess()) {
            $groups['Shows'] = [[
                'Shows Overview',
                \App\Filament\Pages\Shows::getUrl(panel: 'admin'),
                'Shows',
            ]];
        }
        if (\App\Support\AdminModules::isEnabled('inventory')
            && \App\Filament\Resources\InventoryItemResource::canAccess()) {
            $groups['Inventory'] = [[
                'All Inventory',
                \App\Filament\Resources\InventoryItemResource::getUrl('index'),
                'Inventory',
            ]];
        }
    } else {
        $groups = isset($groups['Inventory']) ? ['Inventory' => $groups['Inventory']] : [];
    }
    if (\App\Support\AdminModules::isEnabled('fulfillment') && \App\Filament\Resources\FulfillmentResource::canAccess()) {
        $groups['Fulfillment'] = \App\Support\FulfillmentNav::links();
        // Make the full Fulfillment Center queue directly accessible to managers.
        if ($user?->isFulfillmentAdmin() && \App\Filament\Resources\FulfillmentResource::canAccess()) {
            array_unshift($groups['Fulfillment'], [
                'Fulfillment Center',
                \App\Filament\Resources\FulfillmentResource::getUrl('index'),
                'Fulfillment',
                'heroicon-o-truck',
            ]);
        }
    }
    if (\App\Filament\Pages\Timekeeping::canAccess()) {
        $groups['Timekeeping'] = [['My Timekeeping', \App\Filament\Pages\Timekeeping::getUrl(panel:'admin')]];
    }
    $directGroups = ['Timekeeping'];
}

// The route registry is the source of truth for each link's access.
foreach ($groups as $label => $links) {
    $groups[$label] = array_values(array_filter($links, function ($link) use ($user) {
        if ($link[1] === '#') return true;
        if ($link[0] === 'Streamer Loans') {
            return ($user?->isAdmin() || $user?->isOwner())
                && \App\Support\AdminModules::isEnabled('payouts')
                && ! \App\Support\NavVisibility::isHiddenForUser(\App\Filament\Resources\StreamerLoanResource::class, $user);
        }
        try {
            // Reuse the URL -> controller resolution when the same link is
            // represented more than once in the menu. Authorization remains
            // checked for each render and is never cached across users.
            static $controllers = [];
            if (! array_key_exists($link[1], $controllers)) {
                $request = \Illuminate\Http\Request::create($link[1]);
                $route = app('router')->getRoutes()->match($request);
                $controllers[$link[1]] = $route->getAction('controller');
            }
            $class = $controllers[$link[1]];
            $class = is_string($class) ? explode('@', $class)[0] : null;
            if ($class && method_exists($class, 'canAccess')) {
                return $class::canAccess() && ! \App\Support\NavVisibility::isHiddenForUser($class, $user);
            }
            return true;
        } catch (\Throwable) { return false; }
    }));
    if ($groups[$label] === []) unset($groups[$label]);
}
$ownerLabels = ['Messages & Announcements','Import Status','Show Data Audit','Show Ingestion Logs','Whatnot Scraper','Streamer Login Notifications','Whatnot API Control Center','Whatnot API Changes','Email Log'];
$ownerLinks = $user?->isOwner() ? array_values(array_filter($groups['Admin'] ?? [], fn ($link) => in_array($link[0], $ownerLabels, true))) : [];
if (isset($groups['Admin'])) $groups['Admin'] = array_values(array_filter($groups['Admin'], fn ($link) => ! in_array($link[0], $ownerLabels, true)));
@endphp

<nav class="vx-desktop-mega-nav" aria-label="Primary navigation">
@php $builtInLogo = $navLogo === asset('images/vb-logo-sidebar.svg') && file_exists(public_path('images/vx-nav-logo-light.svg')); @endphp
<a class="vx-mega-brand" href="{{ $homeUrl }}" wire:navigate.hover aria-label="VortexOps dashboard">
    @if ($navLogo)
        <img src="{{ $builtInLogo ? asset('images/vx-nav-logo-dark.svg') : $navLogo }}" alt="" aria-hidden="true" onerror="this.style.display='none'">
    @endif
    <span class="vx-mega-wordmark">VortexOps</span>
</a>
@php $onHome = '/'.trim(request()->path(), '/') === '/'.trim((string) parse_url((string) $homeUrl, PHP_URL_PATH), '/'); @endphp
<a @class(['vx-mega-home', 'is-here' => $onHome]) href="{{ $homeUrl }}" wire:navigate.hover @if($onHome) aria-current="page" @endif><x-filament::icon icon="heroicon-o-home"/><span>{{ $homeLabel }}</span></a>
@foreach($groups as $label=>$links)
@if(in_array($label, $directGroups ?? [], true) && count($links) === 1)
@php $here = request()->routeIs('filament.admin.pages.timekeeping', 'filament.admin.pages.time-attendance'); @endphp
<a class="vx-mega-trigger @if($here) is-here @endif" href="{{ $links[0][1] }}" wire:navigate.hover @if($here) aria-current="page" @endif><x-filament::icon icon="heroicon-o-clock" class="vx-mega-group-icon"/><span>{{ $label }}</span></a>
@continue
@endif
<div class="vx-mega-group" x-data="{open:false}" @mouseenter="open=true" @mouseleave="open=false" @focusin="open=true" @focusout="if (!$el.contains($event.relatedTarget)) open=false" @click.outside="open=false" @keydown.escape.window="open=false">
@php
    // The section you are in stays highlighted: any page at or under one of its links.
    $currentPath = '/'.trim(request()->path(), '/');
    $linkPath = fn ($url) => '/'.trim((string) parse_url((string) $url, PHP_URL_PATH), '/');
    $isCurrent = fn ($url) => ($p = $linkPath($url)) !== '/admin' && $p !== '/' && ($currentPath === $p || str_starts_with($currentPath, $p.'/'));
    $groupHere = collect($links)->contains(fn ($l) => $l[1] !== '#' && $isCurrent($l[1]));
    // Ledger charge routes belong to Payroll even when accessed from a report.
    $isLedgerRoute = str_starts_with($currentPath, '/admin/whatnot-ledgers');
    if ($isLedgerRoute) $groupHere = $label === 'Payroll';
@endphp
@php $groupIcon = match($label) { 'Shows' => 'heroicon-o-video-camera', 'Fulfillment' => 'heroicon-o-cube', 'Inventory' => 'heroicon-o-archive-box', 'Payroll' => 'heroicon-o-wallet', 'Reports' => 'heroicon-o-document-chart-bar', 'Admin' => 'heroicon-o-cog-6-tooth', default => 'heroicon-o-squares-2x2' }; @endphp
<button type="button" @class(['vx-mega-trigger', 'is-here' => $groupHere]) :aria-expanded="open" @click="open=!open"><x-filament::icon :icon="$groupIcon" class="vx-mega-group-icon"/><span>{{ $label }}</span><x-filament::icon icon="heroicon-m-chevron-down" class="vx-mega-chevron"/></button>
@php $sectioned = collect($links)->contains(fn ($l) => isset($l[2])); $lastSection = null; @endphp
<div @class(['vx-mega-menu', 'vx-mega-menu--wide' => count($links) >= 8]) x-cloak x-show="open" x-transition>
@unless($sectioned)<div class="vx-mega-menu-title">{{ $label }}</div>@endunless
@foreach($links as $link)
@php [$text,$url] = $link; $section = $link[2] ?? null; $icon = \App\Support\NavIcons::for($link); @endphp
@if($sectioned && $section !== $lastSection)<div @class(['vx-mega-menu-title', 'vx-mega-section' => $lastSection !== null])>{{ $section }}</div>@php $lastSection = $section; @endphp@endif
<a href="{{ $url }}" wire:navigate.hover @click="open=false" @class(['has-icon' => $icon, 'is-current' => $url !== '#' && $isCurrent($url)]) @if($url !== '#' && $isCurrent($url)) aria-current="page" @endif>@if($icon)<x-filament::icon :icon="$icon" class="vx-mega-link-icon"/>@endif<span>{{ $text }}</span>@unless($icon)<x-filament::icon icon="heroicon-m-chevron-right"/>@endunless</a>
@endforeach
@if($label==='Admin' && $ownerLinks)
<details class="vx-owner-tools"><summary>Owner tools <span aria-hidden="true">⌄</span></summary><div>@foreach($ownerLinks as $link)<a href="{{ $link[1] }}" wire:navigate.hover @click="open=false" class="has-icon">@if($ownerIcon = \App\Support\NavIcons::for($link))<x-filament::icon :icon="$ownerIcon" class="vx-mega-link-icon"/>@endif<span>{{ $link[0] }}</span></a>@endforeach</div></details>
@endif
</div></div>
@endforeach
@if($user?->canSwitchChannels() && ! ($user?->isFulfillment() || $user?->isFulfillmentAdmin()) )
@if(\App\Support\BusinessContext::appliesToCurrentPage() && \App\Support\BusinessContext::available()->isNotEmpty())<div class="vx-mega-channel vx-mega-business">@livewire('business-switcher')</div>@endif
<div class="vx-mega-channel vx-mega-channel--trailing">@livewire('channel-switcher')</div>
@endif
@unless($assistantOnly)
<button type="button" class="vx-search-trigger" data-vx-search-trigger aria-label="Open search" aria-keyshortcuts="Control+K Meta+K" title="Search">
<x-filament::icon icon="heroicon-o-magnifying-glass"/>
</button>
@endunless
</nav>
@include('filament.components.mobile-navigation')




<style>
/* One desktop header layout: Filament topbar utilities above the shared primary navigation. */
@media (min-width:1024px) {
  .fi-topbar {
    position:relative!important;display:flex!important;align-items:flex-start!important;
    height:110px!important;min-height:110px!important;
    width:100%!important;max-width:100%!important;
    padding:0 22px!important;overflow:visible!important;
    background:linear-gradient(105deg,#24133f,#301650 55%,#18122f)!important;
    color:#fff!important;border-bottom:1px solid rgba(255,255,255,.18)!important;
  }
  .fi-topbar-ctn {overflow:visible!important;z-index:50!important}
  .fi-topbar .fi-topbar-start {min-width:0!important;flex:1 1 auto!important}
  .fi-topbar .fi-topbar-end {
    position:absolute!important;top:7px!important;right:22px!important;
    height:51px!important;display:flex!important;align-items:center!important;
    margin:0!important;gap:10px!important;z-index:3!important;
    max-width:calc(100% - 260px)!important;
  }
  /* Do not tint descendant dropdown items: only the top-level utility controls are light. */
  .fi-topbar .fi-topbar-end > :is(button,a) {color:#f7f3ff}
  .fi-topbar .fi-global-search {
    display:block!important;visibility:visible!important;position:relative!important;
    flex:0 1 280px!important;width:clamp(190px,24vw,280px)!important;
    min-width:190px!important;max-width:280px!important;
  }
  .fi-topbar .fi-global-search input {
    background:rgba(255,255,255,.12)!important;color:#fff!important;
    -webkit-text-fill-color:#fff!important;caret-color:#fff!important;
    border:1px solid rgba(255,255,255,.24)!important;
    border-radius:999px!important;
  }
  .fi-topbar .fi-global-search input::placeholder {color:#e3d9f5!important;opacity:1}
  /* Filament paints the input wrapper, not only the input itself. */
  .fi-topbar .fi-global-search :is(.fi-input-wrp,.fi-global-search-field) {
    background:rgba(255,255,255,.11)!important;
    border:1px solid rgba(255,255,255,.23)!important;
    border-radius:13px!important;
    box-shadow:none!important;
    color:#f8f5ff!important;
  }
  .fi-topbar .fi-global-search :is(.fi-input-wrp,.fi-global-search-field) :is(input,svg,button) {color:#f8f5ff!important}
  .fi-topbar .fi-global-search input {background:transparent!important;border:0!important;border-radius:13px!important}
  .fi-topbar .fi-global-search :is(input,.fi-input-wrp) :focus-visible {outline-color:#d7baff!important}

  /* Compact command-palette presentation for Filament's permission-filtered search. */
  .fi-topbar .fi-global-search {width:clamp(240px,27vw,385px)!important;max-width:385px!important}
  .fi-topbar .fi-global-search .fi-input-wrp {
    min-height:42px!important;border-radius:22px!important;
    background:rgba(255,255,255,.1)!important;
    border:1px solid rgba(212,184,255,.35)!important;
  }
  .fi-topbar .fi-global-search .fi-input-wrp:focus-within {
    border-color:#b78aff!important;box-shadow:0 0 0 3px rgba(169,111,255,.2)!important;
  }
  .fi-global-search-results-ctn {
    border-radius:16px!important;border:1px solid #e5ddf4!important;
    box-shadow:0 24px 65px rgba(18,10,45,.24)!important;
    max-height:min(75dvh,660px)!important;overflow-y:auto!important;
  }
  .fi-global-search-results-ctn a {border-radius:9px!important}
  .fi-global-search-results-ctn a:is(:hover,:focus-visible) {background:#f1e9ff!important}
  .dark .fi-global-search-results-ctn {background:#231b33!important;border-color:#54416b!important;color:#f5f0ff!important}
  .dark .fi-global-search-results-ctn a:is(:hover,:focus-visible) {background:#44305d!important}
  .fi-topbar .fi-global-search-results-ctn {
    z-index:110!important;color:#1d1732!important;
  }
  .fi-topbar nav.vx-desktop-mega-nav {
    position:absolute!important;left:22px!important;right:22px!important;
    bottom:0!important;top:auto!important;z-index:2!important;
    box-sizing:border-box!important;display:flex!important;align-items:center!important;
    justify-content:flex-start!important;flex-wrap:nowrap!important;
    height:49px!important;min-height:49px!important;
    width:calc(100% - 44px)!important;max-width:none!important;
    padding:0!important;margin:0!important;gap:5px!important;
    overflow:visible!important;border-top:1px solid rgba(255,255,255,.14)!important;
    background:transparent!important;
  }
  .fi-topbar .vx-mega-brand {
    position:absolute!important;top:-54px!important;left:0!important;
    width:auto!important;max-width:225px!important;height:48px!important;
    display:flex!important;align-items:center!important;flex:none!important;
  }
  .fi-topbar .vx-mega-brand img {width:auto!important;max-width:38px!important;max-height:38px!important;object-fit:contain}
  .fi-topbar .vx-mega-wordmark {display:inline-block!important;color:#fff!important;font-size:21px!important;font-weight:800!important;letter-spacing:-.04em!important;white-space:nowrap!important}
  .fi-topbar .vx-mega-brand {gap:8px!important;max-width:240px!important}
  .fi-topbar .vx-mega-brand img {min-width:28px!important}
  .fi-topbar .vx-mega-brand .vx-logo-on-light {display:none!important}
  .fi-topbar .vx-mega-brand .vx-logo-on-dark {display:block!important}
  .fi-topbar .vx-mega-home,.fi-topbar .vx-mega-trigger {
    display:inline-flex!important;align-items:center!important;justify-content:center!important;
    flex:0 0 auto!important;gap:7px!important;
    min-height:39px!important;height:39px!important;
    padding:0 12px!important;font-size:13px!important;font-weight:600!important;
    color:#f8f5ff!important;background:transparent!important;
    border:1px solid transparent!important;border-radius:10px!important;
    text-decoration:none!important;white-space:nowrap!important;
  }
  .fi-topbar .vx-mega-home svg,.fi-topbar .vx-mega-trigger svg {width:18px!important;height:18px!important;flex-shrink:0!important;color:currentColor!important;display:block!important}
  .fi-topbar .vx-mega-trigger .vx-mega-chevron {width:12px!important;height:12px!important}
  .fi-topbar .vx-mega-home:is(:hover,:focus-visible),.fi-topbar .vx-mega-trigger:is(:hover,:focus-visible),
  .fi-topbar .vx-mega-home.is-here,.fi-topbar .vx-mega-trigger.is-here {
    background:rgba(168,118,247,.26)!important;color:#fff!important;
    border-color:rgba(225,206,255,.15)!important;
  }
  .fi-topbar .vx-mega-group {position:relative!important;flex:0 0 auto!important;z-index:5!important}
  .fi-topbar .vx-mega-menu {
    position:absolute!important;top:100%!important;left:0!important;z-index:100!important;
    margin-top:0!important;padding:14px!important;
    background:#fff!important;color:#19122e!important;
    border:1px solid #e7dff5!important;border-radius:14px!important;
    box-shadow:0 22px 55px rgba(20,10,50,.28)!important;
    max-height:min(75vh,680px)!important;overflow-y:auto!important;
  }
  .fi-topbar .vx-mega-menu--wide {display:grid;grid-template-columns:repeat(2,minmax(185px,1fr));gap:4px 12px;width:min(535px,calc(100vw - 40px))}
  .fi-topbar .vx-mega-menu--wide .vx-mega-menu-title,.fi-topbar .vx-mega-menu--wide .vx-owner-tools {grid-column:1/-1}
  .fi-topbar .vx-mega-menu a {color:#251d3d!important}
  .fi-topbar .vx-mega-menu a.is-current {background:#eee4ff!important;color:#5915b6!important}
  .dark .fi-topbar .vx-mega-menu {background:#241c35!important;color:#f5f0ff!important;border-color:#55446d!important}
  .dark .fi-topbar .vx-mega-menu a {color:#f5f0ff!important}
  .dark .fi-topbar .vx-mega-menu a:hover,.dark .fi-topbar .vx-mega-menu a.is-current {background:#483066!important;color:#fff!important}
  .fi-topbar .fi-topbar-end .fi-icon-btn {color:#f7f3ff!important}
  .fi-topbar .fi-topbar-end .fi-icon-btn:hover {background:rgba(255,255,255,.12)!important;color:#fff!important}
  .fi-topbar .vx-mega-channel {align-self:center!important}
  .fi-topbar .vx-mega-channel :is(.vx-picker-btn,.vx-channel-switcher-select) {color:#fff!important}
  .fi-topbar .vx-mega-channel :is(.vx-channel-switcher-select-wrp,.vx-picker-trigger) {background:rgba(126,74,199,.35)!important;border-color:rgba(255,255,255,.24)!important}

  .fi-topbar .vx-mega-channel {flex:0 0 170px!important;min-width:170px!important;max-width:170px!important}
  .fi-topbar .vx-mega-channel--trailing {margin-left:auto!important}
  .fi-topbar .vx-mega-channel .vx-picker-value {overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important}
  .fi-topbar .vx-mega-channel :is(.vx-channel-switcher-select,.vx-picker-value) {font-size:12px!important}
  .fi-topbar .vx-communications-shortcut {
    display:inline-flex;align-items:center;gap:7px;flex:0 0 auto;
    color:#f8f5ff!important;text-decoration:none!important;font-size:12px;
    font-weight:600;padding:8px 10px;border-radius:9px;
  }
  .fi-topbar .vx-communications-shortcut svg {width:17px;height:17px}
  .fi-topbar .vx-communications-shortcut:hover,.fi-topbar .vx-communications-shortcut:focus-visible {
    background:rgba(168,118,247,.26);color:#fff!important;
  }
  .fi-topbar .vx-search-trigger {display:none!important}
}
@media (min-width:1024px) and (max-width:1350px) {
  .fi-topbar .vx-mega-home,.fi-topbar .vx-mega-trigger {font-size:12px!important;padding-inline:7px!important;gap:4px!important}
  .fi-topbar nav.vx-desktop-mega-nav {gap:2px!important}
  .fi-topbar .vx-mega-channel {max-width:110px!important;flex-basis:110px!important}
}
@media (min-width:1024px) and (max-width:1150px) {
  .fi-topbar .vx-mega-home,.fi-topbar .vx-mega-trigger {padding-inline:5px!important}
  .fi-topbar .vx-mega-trigger .vx-mega-chevron {display:none!important}
  .fi-topbar .vx-mega-channel {max-width:44px!important;flex-basis:44px!important}
}
@media (max-width:1023px) {
  .vx-desktop-mega-nav {display:none!important}
  .vx-search-trigger {display:none!important}
}
@media (prefers-reduced-motion:reduce) {.vx-mega-menu,.vx-mega-trigger {transition:none!important}}
</style>


<script>
(() => {
    const key = 'vortex:visited-pages';
    const read = () => {
        try { const value = JSON.parse(sessionStorage.getItem(key) || '[]'); return Array.isArray(value) ? value.filter(url => { try { const parsed = new URL(url); return parsed.origin === location.origin && parsed.pathname.startsWith('/admin'); } catch { return false; } }) : []; }
        catch { return []; }
    };
    const save = pages => { try { sessionStorage.setItem(key, JSON.stringify(pages.slice(-100))); } catch {} };
    const track = () => {
        const pages = read();
        if (!pages.length && document.referrer) {
            try { const ref = new URL(document.referrer); if (ref.origin === location.origin && ref.pathname.startsWith('/admin') && ref.href !== location.href) pages.push(ref.href); } catch {}
        }
        if (pages[pages.length - 1] !== location.href) pages.push(location.href);
        save(pages);
    };
    window.vortexGoBack = fallback => {
        const pages = read();
        while (pages.length && pages[pages.length - 1] === location.href) pages.pop();
        const previous = pages[pages.length - 1];
        save(pages);
        if (previous) { location.assign(previous); return; }
        if (history.length > 1) { history.back(); return; }
        location.assign(fallback);
    };
    if (!window.vortexHistoryTrackingInstalled) {
        window.vortexHistoryTrackingInstalled = true;
        document.addEventListener('livewire:navigated', track);
        window.addEventListener('pageshow', track);
        window.addEventListener('popstate', () => {
            const pages = read();
            const index = pages.lastIndexOf(location.href);
            if (index >= 0) save(pages.slice(0, index + 1));
            else track();
        });
    }
    track();
})();
</script>
