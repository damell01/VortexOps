<?php

namespace App\Providers\Filament;

use App\Models\Setting;
use App\Models\WhatnotChannel;
use App\Support\ChannelContext;
use App\Filament\Plugins\ScopedQuickCreatePlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Support\AdminModules;
use App\Http\Middleware\EnforceNavVisibility;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use App\Filament\Pages\DashboardImproved;
use App\Filament\Pages\Auth\Login;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\FontProviders\GoogleFontProvider;
use Filament\View\PanelsRenderHook;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Read branding from settings (cached 1hr), fall back to defaults on fresh install
        try {
            $brandName    = Setting::get('brand_name',    'VortexOps');
            $primaryColor = Setting::get('primary_color', '#7c3aed');
            $logoPath     = Setting::get('logo_path');
        } catch (\Exception) {
            $brandName    = 'VortexOps';
            $primaryColor = '#7c3aed';
            $logoPath     = null;
        }

        if (! preg_match('/^#[0-9a-fA-F]{3,8}$/', $primaryColor)) {
            $primaryColor = '#7c3aed';
        }

        // Both resolved as Closures (not plain strings) so they're evaluated at
        // render time rather than here at panel-registration time — registration
        // runs before the session middleware boots, so ChannelContext (session-
        // backed) would always read as unscoped if resolved eagerly here.
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset()
            ->profile(isSimple: false)
            ->brandName(fn (): string => static::resolveBrandName(ChannelContext::current(), $brandName, $logoPath))
            ->brandLogo(fn (): ?string => static::resolveBrandLogo(ChannelContext::current(), $logoPath))
            ->brandLogoHeight('2.75rem')
            ->font('Geist', provider: GoogleFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Needed so the sidebar can be opened/closed on desktop. Filament
            // renders this as a chevron next to the drawer hamburger; the
            // stylesheet hides the duplicate and redraws the remaining control
            // as a hamburger so there is exactly one, consistent toggle.
            // Desktop uses a consolidated top navigation with dropdown groups.
            // Filament still provides its native mobile navigation drawer.
            ->topNavigation()
            ->navigation(fn (NavigationBuilder $builder): NavigationBuilder => static::buildTopNavigation($builder))
            // Mobile-optimized: 6xl on desktop, full width on mobile
            ->maxContentWidth(\Filament\Support\Enums\Width::Full)
            ->globalSearchKeyBindings(['mod+k', '/'])
            ->globalSearchDebounce('200ms')
            ->colors([
                'primary' => Color::hex($primaryColor),
                'gray'    => Color::Zinc,
                'info'    => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger'  => Color::Rose,
            ]);

        $isAuthenticatedAdminView = fn (): bool => auth()->check();
        $hasViteManifest = fn (): bool => file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));

        $pwaIconsExist = fn (): bool => file_exists(public_path('icons/icon-192.png'));

        return $panel
            ->spa(hasPrefetching: false)
            ->databaseNotifications()
            // 300s meant a streamer could sit for five minutes after an admin
            // requested changes before the bell showed anything, which read as
            // "no notification was sent" when one had been written instantly.
            ->databaseNotificationsPolling('30s')
            ->navigationGroups(array_map(
                // Collapse every group except the primary "Streams" workflow, so the
                // sidebar stays compact — you expand the group you need.
                fn (string $group): NavigationGroup => $group === 'Streams'
                    ? NavigationGroup::make($group)
                    : NavigationGroup::make($group)->collapsed(),
                AdminModules::visibleNavigationGroups(),
            ))
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => <<<'HTML'
                <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
                HTML,
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => $hasViteManifest() && $isAuthenticatedAdminView()
                    ? Blade::render("@vite(['resources/js/app.js'])")
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function () use ($brandName, $primaryColor, $pwaIconsExist): string {
                    $color  = htmlspecialchars($primaryColor, ENT_QUOTES);
                    $bname  = htmlspecialchars($brandName, ENT_QUOTES);
                    $icons  = $pwaIconsExist();
                    $touch  = $icons ? '<link rel="apple-touch-icon" href="/icons/icon-180.png">' : '';
                    $favicon = $icons ? '<link rel="icon" type="image/png" sizes="32x32" href="/icons/icon-32.png">' : '';
                    return implode('', [
                        '<link rel="manifest" href="/manifest.json">',
                        "<meta name=\"theme-color\" content=\"{$color}\">",
                        '<meta name="mobile-web-app-capable" content="yes">',
                        '<meta name="apple-mobile-web-app-capable" content="yes">',
                        '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">',
                        "<meta name=\"apple-mobile-web-app-title\" content=\"{$bname}\">",
                        $touch,
                        $favicon,
                    ]);
                },
            )
            // Account card pinned to the bottom of the sidebar. Filament renders
            // no sidebar footer in this panel (.fi-sidebar-footer was absent from
            // the DOM), so the design's user block is supplied through this hook.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => ! $isAuthenticatedAdminView()
                    ? ''
                    : Blade::render('@include(\'filament.components.sidebar-account\')'),
            )
            // Feedback as a navigation item rather than a button floating over
            // every page. SIDEBAR_NAV_END so it sits under the real links
            // instead of competing with them for the top of the list.
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => ! $isAuthenticatedAdminView()
                    ? ''
                    : Blade::render('@include(\'filament.components.sidebar-feedback\')'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => ! $isAuthenticatedAdminView()
                    ? ''
                    : Blade::render(<<<'HTML'
                    @livewire('feedback-widget')
                    <script>
                    (function() {
                        let notificationPanelOpen = false;
                        const notificationBtn = document.querySelector('[aria-label*="notification"], [aria-label*="Notification"]');

                        if (notificationBtn) {
                            notificationBtn.addEventListener('click', function(e) {
                                // Let the click propagate first to open the panel
                                setTimeout(() => {
                                    const panel = document.querySelector('[role="dialog"]') ||
                                                  document.querySelector('.fi-dropdown-panel') ||
                                                  document.querySelector('[class*="notification"]');

                                    if (panel && panel.offsetParent !== null) {
                                        notificationPanelOpen = true;
                                    } else if (notificationPanelOpen) {
                                        // If panel is closing, toggle the button to close it
                                        notificationBtn.click();
                                        notificationPanelOpen = false;
                                    }
                                }, 10);
                            });
                        }

                        // Alternative: Listen for panel visibility changes
                        document.addEventListener('click', function(e) {
                            const notificationPanel = document.querySelector('[class*="notification"]');
                            if (notificationPanel && !notificationPanel.contains(e.target) &&
                                e.target !== notificationBtn && !notificationBtn.contains(e.target)) {
                                notificationPanelOpen = false;
                            }
                        });
                    })();
                    </script>
                    HTML),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => ! $isAuthenticatedAdminView() ? '' : view('filament.components.mobile-tabbar'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => (auth()->user()?->canSwitchChannels() ?? false)
                    ? Blade::render("@livewire('channel-switcher')")
                    : '',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => ! $isAuthenticatedAdminView()
                    ? ''
                    : view('components.toast-container'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => ! $isAuthenticatedAdminView()
                    ? ''
                    : Blade::render(<<<'HTML'
                    <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        // Show keyboard shortcuts hint in console
                        const shortcuts = 'Press ? to see keyboard shortcuts';
                        console.info('%c' + shortcuts, 'color: #7c3aed; font-size: 12px; font-weight: bold;');
                    });
                    </script>
                    HTML),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => ! $isAuthenticatedAdminView()
                    ? ''
                    : view('filament.components.camera-barcode-scanner'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => ! $isAuthenticatedAdminView()
                    ? ''
                    : view('filament.components.camera-photo-capture'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => <<<'HTML'
                <script>
                // Mobile sidebar touch/swipe gesture handler
                // Note: Filament v5 uses Alpine.js for sidebar state ($store.sidebar.isOpen)
                // The toggle buttons already have x-on:click handlers, so we just add swipe support
                function initMobileSidebarGestures() {
                    const sidebar = document.querySelector('.fi-sidebar');
                    if (!sidebar) return;

                    let touchStartX = 0;
                    let touchEndX = 0;

                    // Swipe gesture support
                    document.addEventListener('touchstart', (e) => {
                        touchStartX = e.changedTouches[0].screenX;
                    }, false);

                    document.addEventListener('touchend', (e) => {
                        touchEndX = e.changedTouches[0].screenX;
                        const swipeThreshold = 50;
                        const diff = touchStartX - touchEndX;

                        // Check if Alpine store is available
                        if (!window.Alpine) return;

                        // Swipe right to open sidebar (from left edge)
                        if (touchStartX < 50 && diff < -swipeThreshold) {
                            const btn = document.querySelector('.fi-topbar-open-sidebar-btn');
                            if (btn) btn.click();
                        }
                        // Swipe left to close sidebar
                        else if (diff > swipeThreshold) {
                            const btn = document.querySelector('.fi-topbar-close-sidebar-btn');
                            if (btn) btn.click();
                        }
                    }, false);

                    // Close sidebar when clicking on a nav link (better UX on mobile)
                    const navItems = sidebar.querySelectorAll('a[href]');
                    navItems.forEach(item => {
                        item.addEventListener('click', () => {
                            const closeBtn = document.querySelector('.fi-topbar-close-sidebar-btn');
                            if (closeBtn && closeBtn.offsetParent !== null) { // Only if visible
                                setTimeout(() => closeBtn.click(), 100);
                            }
                        });
                    });
                }

                // Initialize once Alpine is ready
                document.addEventListener('alpine:init', initMobileSidebarGestures);
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(initMobileSidebarGestures, 100);
                });

                // Reinitialize on Livewire updates
                if (window.Livewire) {
                    Livewire.hook('morph.updated', () => {
                        setTimeout(initMobileSidebarGestures, 100);
                    });
                }
                </script>
                HTML,
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => file_exists(public_path('sw.js')) ? <<<'HTML'
                    <script>
                    if ('serviceWorker' in navigator) {
                        window.addEventListener('load', () => {
                            navigator.serviceWorker.register('/sw.js', { scope: '/' })
                                .catch(() => {});
                        });
                    }
                    </script>
                    HTML : '',
            )
            // ── Login page: soft gradient background + light glassmorphic card ───
            // Everything lives inside AUTH_LOGIN_FORM_BEFORE/AFTER — the only hooks
            // confirmed to actually render on this page — rather than
            // SIMPLE_LAYOUT_START/END, which don't fire reliably here.
            // ── Login page: gradient banner + heading inside the card ────────────
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => <<<'HTML'
                <div class="vx-login-hero">
                    <div class="vx-wave-banner">
                        <div class="vx-wave-banner-inner">
                            <svg viewBox="0 0 100 100" width="26" height="26" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">
                                <defs><mask id="vx-lm2"><rect width="100" height="100" fill="white"/><rect x="0" y="19.5" width="100" height="9" fill="black"/></mask></defs>
                                <path mask="url(#vx-lm2)" d="M 23,15 L 77,15 Q 87,15 82,25 L 53,80 Q 50,87 47,80 L 18,25 Q 13,15 23,15 Z" stroke="#fff" stroke-width="6" stroke-linejoin="round" fill="none"/>
                                <path d="M 30,24 L 70,24 Q 79,24 74.5,32 L 52.5,75 Q 50,81 47.5,75 L 25.5,32 Q 21,24 30,24 Z" stroke="#fff" stroke-width="5.5" stroke-linejoin="round" fill="none"/>
                                <path d="M 23,15 L 77,15" stroke="#fff" stroke-width="6" stroke-linecap="round"/>
                                <path d="M 30,24 L 70,24" stroke="#fff" stroke-width="5.5" stroke-linecap="round"/>
                            </svg>
                            <div class="vx-wave-banner-word">VORTEX<span>Operations Platform</span></div>
                        </div>
                    </div>
                    <div class="vx-login-heading">
                        <h1>Welcome Back!</h1>
                        <p>Sign in to manage your operations hub.</p>
                    </div>
                </div>
                HTML,
            )
            // ── Login page: footer credit inside the card, below the form ────────
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => <<<'HTML'
                <div class="vx-login-footer">Built by DBell Creations</div>
                HTML,
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                DashboardImproved::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
                ScopedQuickCreatePlugin::make()
                    ->excludes([
                        \App\Filament\Resources\PayoutResource::class,
                        \App\Filament\Resources\WeeklyPayoutBatchResource::class,
                        \App\Filament\Resources\ActivityLogResource::class,
                        // Quick Create inserts with whatever the form supplies.
                        // These records cannot exist without a parent show, so
                        // an empty create hits a NOT NULL show_id and 500s.
                        // They are created from the show they belong to.
                        \App\Filament\Resources\ShipmentResource::class,
                        \App\Filament\Resources\ShippingSurchargeResource::class,
                        \App\Filament\Resources\DeductionRequestResource::class,
                        \App\Filament\Resources\StreamerLogResource::class,
                    ])
                    ->hidden(fn () => ! (auth()->user()?->isAdmin() || auth()->user()?->isOwner() || auth()->user()?->isStreamer())),
            ])
            ->authMiddleware([
                Authenticate::class,
                // After Authenticate, so there is a user to read roles from.
                // Central enforcement of the per-role page visibility set on
                // Roles & Permissions — see the middleware for why it does not
                // live in each class's canAccess().
                EnforceNavVisibility::class,
            ]);
    }


    /**
     * Consolidated desktop navigation. Each top-level item is a real landing
     * page and its children become Filament's native dropdown menu. Existing
     * canAccess()/module checks remain the source of truth for visibility.
     */
    private static function buildTopNavigation(NavigationBuilder $builder): NavigationBuilder
    {
        $dashboard = NavigationItem::make('Dashboard')
            ->icon('heroicon-o-home')
            ->url(DashboardImproved::getUrl(panel: 'admin'));

        $groups = [];
        $item = static fn (string $label, string $icon, string $url): NavigationItem =>
            NavigationItem::make($label)->icon($icon)->url($url);

        if (AdminModules::isEnabled('streams') && \App\Filament\Pages\Shows::canAccess()) {
            $shows = [];
            $shows[] = $item('Shows Overview', 'heroicon-o-squares-2x2', \App\Filament\Pages\Shows::getUrl(panel: 'admin'));
            if (\App\Filament\Resources\ShowResource::canAccess()) $shows[] = $item('All Shows', 'heroicon-o-calendar-days', \App\Filament\Resources\ShowResource::getUrl('index'));
            if (\App\Filament\Pages\EndOfStreamForm::canAccess()) $shows[] = $item('End of Stream', 'heroicon-o-clipboard-document-check', \App\Filament\Pages\EndOfStreamForm::getUrl(panel: 'admin'));
            if (\App\Filament\Resources\StreamerResource::canAccess()) $shows[] = $item('Team / Streamers', 'heroicon-o-users', \App\Filament\Resources\StreamerResource::getUrl('index'));
            if (\App\Filament\Pages\ShowDataAudit::canAccess()) $shows[] = $item('Show Data Audit', 'heroicon-o-chart-bar-square', \App\Filament\Pages\ShowDataAudit::getUrl(panel: 'admin'));
            $groups[] = NavigationGroup::make('Shows')->icon('heroicon-o-video-camera')->items($shows);
        }

        if (AdminModules::isEnabled('inventory') && \App\Filament\Resources\InventoryItemResource::canAccess()) {
            $inventory = [
                $item('Inventory Overview', 'heroicon-o-squares-2x2', \App\Filament\Pages\InventoryOverview::getUrl(panel: 'admin')),
                $item('All Inventory', 'heroicon-o-archive-box', \App\Filament\Resources\InventoryItemResource::getUrl('index')),
                $item('Quick Add Stock', 'heroicon-o-plus-circle', \App\Filament\Resources\InventoryItemResource::getUrl('quick-add')),
            ];
            if (\App\Filament\Pages\InventoryScanner::canAccess()) $inventory[] = $item('Scan Inventory', 'heroicon-o-qr-code', \App\Filament\Pages\InventoryScanner::getUrl(panel: 'admin'));
            if (\App\Filament\Pages\InventoryReport::canAccess()) $inventory[] = $item('Inventory Reports', 'heroicon-o-chart-bar', \App\Filament\Pages\InventoryReport::getUrl(panel: 'admin'));
            $groups[] = NavigationGroup::make('Inventory')->icon('heroicon-o-cube')->items($inventory);
        }

        if (AdminModules::isEnabled('fulfillment') && \App\Filament\Resources\FulfillmentResource::canAccess()) {
            $url = \App\Filament\Resources\FulfillmentResource::getUrl('index');
            $groups[] = NavigationGroup::make('Fulfillment')->icon('heroicon-o-truck')->items([
                $item('Fulfillment Dashboard', 'heroicon-o-squares-2x2', $url),
                $item('Orders & Shipments', 'heroicon-o-clipboard-document-list', $url),
            ]);
        }

        if (AdminModules::isEnabled('payouts') && \App\Filament\Pages\PayrollOverview::canAccess()) {
            $finance = [$item('Payroll', 'heroicon-o-banknotes', \App\Filament\Pages\PayrollOverview::getUrl(panel: 'admin'))];
            if (\App\Filament\Resources\PayoutResource::canAccess()) $finance[] = $item('Payouts', 'heroicon-o-currency-dollar', \App\Filament\Resources\PayoutResource::getUrl('index'));
            if (\App\Filament\Resources\WeeklyPayoutBatchResource::canAccess()) $finance[] = $item('Pay Runs', 'heroicon-o-calendar-days', \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index'));
            $groups[] = NavigationGroup::make('Finance')->icon('heroicon-o-banknotes')->items($finance);
        }

        if (AdminModules::isEnabled('reporting') && \App\Filament\Pages\Reports::canAccess()) {
            $reports = [$item('Reports & Analytics', 'heroicon-o-chart-bar-square', \App\Filament\Pages\Reports::getUrl(panel: 'admin'))];
            if (\App\Filament\Pages\StreamerAnalytics::canAccess()) $reports[] = $item('Streamer Analytics', 'heroicon-o-users', \App\Filament\Pages\StreamerAnalytics::getUrl(panel: 'admin'));
            if (\App\Filament\Pages\InventoryReport::canAccess()) $reports[] = $item('Inventory Reports', 'heroicon-o-cube', \App\Filament\Pages\InventoryReport::getUrl(panel: 'admin'));
            if (\App\Filament\Resources\WhatnotLedgerResource::canAccess()) $reports[] = $item('Ledger', 'heroicon-o-book-open', \App\Filament\Resources\WhatnotLedgerResource::getUrl('index'));
            $groups[] = NavigationGroup::make('Reports')->icon('heroicon-o-chart-bar')->items($reports);
        }

        if (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) {
            $admin = [];
            if (\App\Filament\Resources\UserResource::canAccess()) $admin[] = $item('Users', 'heroicon-o-users', \App\Filament\Resources\UserResource::getUrl('index'));
            if (\App\Filament\Pages\ShowDataAudit::canAccess()) $admin[] = $item('Show Data Audit', 'heroicon-o-shield-check', \App\Filament\Pages\ShowDataAudit::getUrl(panel: 'admin'));
            if (\App\Filament\Pages\AppSettings::canAccess()) $admin[] = $item('Settings', 'heroicon-o-cog-6-tooth', \App\Filament\Pages\AppSettings::getUrl(panel: 'admin'));
            if ($admin !== []) $groups[] = NavigationGroup::make('Admin')->icon('heroicon-o-cog-6-tooth')->items($admin);
        }

        return $builder->items([$dashboard])->groups($groups);
    }

    /**
     * Precedence: the active channel's own title, else the global brand name —
     * except when falling back to the built-in SVG logo (no channel logo, no
     * global logo), which already contains the wordmark so the text is hidden.
     */
    public static function resolveBrandName(?WhatnotChannel $channel, string $brandName, ?string $logoPath): string
    {
        if ($channel?->display_title) {
            return $channel->display_title;
        }

        if (! $channel?->logo_path && ! $logoPath && file_exists(public_path('images/vb-logo-sidebar.svg'))) {
            return '';
        }

        return $brandName;
    }

    /** Precedence: the active channel's own logo, else the global logo, else the built-in SVG. */
    public static function resolveBrandLogo(?WhatnotChannel $channel, ?string $logoPath): ?string
    {
        if ($channel?->logo_path && file_exists(storage_path('app/public/' . $channel->logo_path))) {
            return asset('storage/' . $channel->logo_path);
        }

        if ($logoPath && file_exists(storage_path('app/public/' . $logoPath))) {
            return asset('storage/' . $logoPath);
        }

        if (file_exists(public_path('images/vb-logo-sidebar.svg'))) {
            return asset('images/vb-logo-sidebar.svg');
        }

        return null;
    }
}