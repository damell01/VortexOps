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
if($assistantOnly){
 $groups['Shows']=array_values(array_filter([
  \App\Filament\Pages\Shows::canAccess() ? ['Show Calendar',\App\Filament\Pages\Shows::getUrl(panel:'admin')] : (\App\Filament\Resources\ShowResource::canAccess() ? ['All Shows',\App\Filament\Resources\ShowResource::getUrl('index')] : null),
  \App\Filament\Pages\ShowLeaderboard::canAccess() ? ['Show Leaderboard',\App\Filament\Pages\ShowLeaderboard::getUrl(panel:'admin')] : null,
 ]));
}elseif($streamerOnly){
 if(\App\Filament\Pages\Shows::canAccess()) $groups['Shows']=array_values(array_filter([
  ['Show Calendar',\App\Filament\Pages\Shows::getUrl(panel:'admin')],
  \App\Filament\Pages\ShowLeaderboard::canAccess() ? ['Show Leaderboard',\App\Filament\Pages\ShowLeaderboard::getUrl(panel:'admin')] : null,
  \App\Filament\Pages\EndOfStreamForm::canAccess() ? ['Log a Show',\App\Filament\Pages\EndOfStreamForm::getUrl(panel:'admin')] : null,
  \App\Filament\Pages\Timekeeping::canAccess() ? ['Timekeeping',\App\Filament\Pages\Timekeeping::getUrl(panel:'admin')] : null,
 ]));
}elseif(\App\Support\AdminModules::isEnabled('streams') && \App\Filament\Pages\Shows::canAccess()) $groups['Shows']=[
 ['Shows Overview',\App\Filament\Pages\Shows::getUrl(panel:'admin')],
 ...(\App\Filament\Pages\ShowLeaderboard::canAccess() ? [['Show Leaderboard',\App\Filament\Pages\ShowLeaderboard::getUrl(panel:'admin')]] : []),
 ...($user?->isAdmin() ? [['Channel Review',\App\Filament\Resources\ShowResource::getUrl('channel-review')]] : []),
 ...(\App\Filament\Resources\ShowResource\Pages\ReviewShowStreamers::canAccess() ? [['Stream Review',\App\Filament\Resources\ShowResource::getUrl('stream-review')]] : []),
 ...($user?->isOwner() ? [['Whatnot Sheet Check',\App\Filament\Pages\WhatnotSheetCheck::getUrl(panel:'admin')]] : []),
 ['Show Report Inbox',\App\Filament\Resources\StreamerLogResource::getUrl('index')],
];
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
 ['Business Profitability',\App\Filament\Pages\ProfitLoss::getUrl(panel:'admin'),'Financial','heroicon-o-currency-dollar'],
 ...(\App\Filament\Pages\ShowPerformance::canAccess() ? [['Show Performance',\App\Filament\Pages\ShowPerformance::getUrl(panel:'admin'),'Financial','heroicon-o-fire']] : []),
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
   ['Messages & Announcements',\App\Filament\Pages\Communications::getUrl(panel:'admin')],
   ['Import Status',\App\Filament\Pages\ImportStatus::getUrl(panel:'admin')],
   ['Show Data Audit',\App\Filament\Pages\ShowDataAudit::getUrl(panel:'admin')],
   ['Show Ingestion Logs',\App\Filament\Resources\ShowIngestionLogResource::getUrl('index')],
   ['Whatnot Scraper',\App\Filament\Pages\WhatnotSyncPage::getUrl(panel:'admin')],
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
$ownerLabels = ['Messages & Announcements','Import Status','Show Data Audit','Show Ingestion Logs','Whatnot Scraper','Email Log'];
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
@php [$text,$url] = $link; $section = $link[2] ?? null; $icon = \App\Support\NavIcons::for($link); @endphp
@if($sectioned && $section !== $lastSection)<div @class(['vx-mega-menu-title', 'vx-mega-section' => $lastSection !== null])>{{ $section }}</div>@php $lastSection = $section; @endphp@endif
<a href="{{ $url }}" @click="open=false" @class(['has-icon' => $icon])>@if($icon)<x-filament::icon :icon="$icon" class="vx-mega-link-icon"/>@endif<span>{{ $text }}</span>@unless($icon)<x-filament::icon icon="heroicon-m-chevron-right"/>@endunless</a>
@endforeach
@if($label==='Admin' && $ownerLinks)
<details class="vx-owner-tools"><summary>Owner tools <span aria-hidden="true">⌄</span></summary><div>@foreach($ownerLinks as $link)<a href="{{ $link[1] }}" @click="open=false" class="has-icon">@if($ownerIcon = \App\Support\NavIcons::for($link))<x-filament::icon :icon="$ownerIcon" class="vx-mega-link-icon"/>@endif<span>{{ $link[0] }}</span></a>@endforeach</div></details>
@endif
</div></div>
@endforeach
@if($user?->canSwitchChannels())
@if(\App\Support\BusinessContext::appliesToCurrentPage() && \App\Support\BusinessContext::available()->isNotEmpty())<div class="vx-mega-channel vx-mega-business">@livewire('business-switcher')</div>@endif
<div class="vx-mega-channel">@livewire('channel-switcher')</div>
@endif
<button type="button" class="vx-search-launch" aria-label="Open global search" title="Search (Ctrl+K)" onclick="window.vortexOpenSearch?.()">
<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="21" height="21"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>
<span class="vx-search-key">Ctrl K</span>
</button>
</nav>
@include('filament.components.mobile-navigation')



<style>
/* The real Filament Livewire search stays mounted; only its presentation changes. */
@media (min-width:1024px) {
 .fi-topbar .fi-global-search { display:none !important; }
 .fi-topbar.vx-search-open .fi-global-search {
  display:block !important; position:fixed !important; inset: min(12vh,100px) auto auto 50% !important;
  transform:translateX(-50%) !important; width:min(720px,calc(100vw - 36px)) !important;
  max-width:720px !important; min-width:0 !important; z-index:10002 !important;
  margin:0 !important; flex:none !important;
 }
 .fi-topbar.vx-search-open .fi-global-search .fi-input-wrp { min-height:62px !important; width:100% !important; }
 .fi-topbar.vx-search-open .fi-global-search input { width:100% !important; min-height:60px !important; font-size:18px !important; }
 .fi-topbar.vx-search-open .fi-global-search input::placeholder { color:#94a3b8 !important; }
 .vx-search-launch { display:flex; align-items:center; justify-content:center; gap:8px; flex:0 0 auto; min-width:44px; height:44px; padding:0 12px; color:#d8b4fe; border:1px solid #493269; border-radius:12px; cursor:pointer; }
 .vx-search-launch:hover,.vx-search-launch:focus-visible { background:#342052; outline:2px solid #a78bfa; outline-offset:2px; }
 .vx-search-key { font-size:11px; color:#a1a1aa; white-space:nowrap; }
 .vx-search-backdrop { position:fixed; inset:0; background:rgba(9,7,24,.78); backdrop-filter:blur(5px); z-index:10000; }
 .vx-search-panel { position:fixed; top:min(12vh,100px); left:50%; transform:translateX(-50%); width:min(720px,calc(100vw - 36px)); height:64px; border-radius:18px; background:#18122b; border:1px solid #8b5cf6; box-shadow:0 25px 90px #0009; z-index:10001; pointer-events:none; }
 .vx-search-hint { position:fixed; top:calc(min(12vh,100px) + 76px); left:50%; transform:translateX(-50%); width:min(720px,calc(100vw - 36px)); padding:10px 14px; border-radius:12px; background:#18122b; color:#c4b5fd; font-size:12px; z-index:10001; pointer-events:none; }
 body.vx-search-modal-open { overflow:hidden; }
}
@media (max-width:1023px) { .vx-search-launch { display:none; } }
</style>
<script>
(() => {
 if(window.vortexSearchLauncherInstalled) return;
 window.vortexSearchLauncherInstalled=true;
 let overlay=null;
 const close=()=>{
  document.querySelectorAll('.fi-topbar.vx-search-open').forEach(el=>el.classList.remove('vx-search-open'));
  overlay?.remove();overlay=null;document.body.classList.remove('vx-search-modal-open');
 };
 window.vortexOpenSearch=()=>{
  const topbar=document.querySelector('.fi-topbar:has(.vx-desktop-mega-nav)');
  const input=topbar?.querySelector('.fi-global-search input');
  if(!topbar||!input){ document.querySelector('.fi-global-search input')?.focus();return; }
  close();
  overlay=document.createElement('div');
  overlay.innerHTML='<div class="vx-search-backdrop"></div><div class="vx-search-panel"></div><div class="vx-search-hint">Search inventory, SKUs and records · Esc to close</div>';
  overlay.querySelector('.vx-search-backdrop').addEventListener('click',close);
  document.body.appendChild(overlay);
  topbar.classList.add('vx-search-open');document.body.classList.add('vx-search-modal-open');
  requestAnimationFrame(()=>input.focus());
 };
 document.addEventListener('keydown',e=>{
  if(e.key==='Escape' && overlay){close();return;}
  if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){
   e.preventDefault();window.vortexOpenSearch();
  }
 });
 document.addEventListener('livewire:navigating',close);
})();
</script>

<style>
@media (min-width: 1024px) {
    .fi-topbar-ctn { min-width: 0 !important; max-width: 100% !important; }
    .fi-topbar:has(.vx-desktop-mega-nav) {
        box-sizing: border-box !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        height: 68px !important;
        min-height: 68px !important;
        display: flex !important;
        flex-wrap: nowrap !important;
        gap: 12px !important;
        padding: 0 20px !important;
        overflow: visible !important;
    }
    .fi-topbar nav.vx-desktop-mega-nav {
        box-sizing: border-box !important;
        flex: 1 1 0% !important;
        width: auto !important;
        min-width: 0 !important;
        max-width: 100% !important;
        height: auto !important;
        min-height: 44px !important;
        padding: 0 !important;
        margin: 0 !important;
        flex-wrap: nowrap !important;
    }
    .fi-topbar .fi-topbar-start { flex: 0 0 auto !important; margin: 0 !important; }
    .fi-topbar .fi-topbar-end {
        box-sizing: border-box !important;
        flex: 0 1 auto !important;
        min-width: 0 !important;
        max-width: 100% !important;
        margin-inline-start: auto !important;
        padding-inline-end: 4px !important;
        gap: 12px !important;
    }
    .fi-topbar .fi-topbar-end > :not(.fi-global-search) { flex-shrink: 0 !important; }
    /* Keep Filament's actual search input clickable across its full visible surface. */
    .fi-topbar .fi-global-search {
        position: relative !important;
        z-index: 5 !important;
        pointer-events: auto !important;
        flex: 0 1 240px !important;
        width: clamp(140px, 16vw, 240px) !important;
        min-width: 0 !important;
        max-width: 240px !important;
        margin: 0 !important;
    }
    .fi-topbar .fi-global-search :is(.fi-input-wrp, .fi-global-search-field, input) {
        box-sizing: border-box !important;
        width: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }
    .fi-topbar .fi-user-menu { flex: 0 0 auto !important; margin: 0 !important; }
}
@media (min-width: 1024px) and (max-width: 1399px) {
    .fi-topbar nav.vx-desktop-mega-nav :is(.vx-mega-home, .vx-mega-trigger) {
        padding-inline: 6px !important;
        font-size: 12px !important;
    }
    .fi-topbar .fi-global-search {
        flex: 0 1 220px !important;
        width: clamp(160px, 18vw, 220px) !important;
        max-width: 220px !important;
    }
    .fi-topbar .fi-global-search input::placeholder { color: #64748b !important; }
    .fi-topbar .fi-global-search:focus-within {
        position: relative !important;
        right: auto !important;
        width: clamp(160px, 18vw, 220px) !important;
        max-width: 220px !important;
        z-index: 60;
    }
    .fi-topbar .fi-global-search:focus-within input::placeholder { color: #64748b !important; }
    .fi-topbar .vx-mega-channel { min-width: 115px !important; max-width: 140px !important; flex-basis: 130px !important; }
}
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
