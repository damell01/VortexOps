<?php

namespace App\Providers\Filament;

use App\Models\Setting;
use App\Models\WhatnotChannel;
use App\Support\ChannelContext;
use Awcodes\QuickCreate\QuickCreatePlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Support\AdminModules;
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
use Filament\View\PanelsRenderHook;
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
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
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
            ->spa(hasPrefetching: true)
            ->databaseNotifications()
            ->databaseNotificationsPolling('300s')
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
                fn (): string => ! $hasViteManifest()
                    ? ''
                    : ($isAuthenticatedAdminView()
                        ? Blade::render("@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/barcode-scanner.js'])")
                        : Blade::render("@vite(['resources/css/app.css'])")),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => <<<'CSS'
                <style>
                /* Mobile sidebar visibility management */
                @media (max-width: 1024px) {
                    .fi-sidebar {
                        position: fixed !important;
                        left: -100% !important;
                        top: 0 !important;
                        height: 100vh !important;
                        width: 280px !important;
                        max-width: 80vw !important;
                        z-index: 40 !important;
                        overflow-y: auto !important;
                        transition: left 0.3s ease-in-out !important;
                    }

                    .fi-sidebar.fi-sidebar-open {
                        left: 0 !important;
                    }

                    /* Show sidebar overlay when open */
                    .fi-sidebar-overlay {
                        position: fixed !important;
                        inset: 0 !important;
                        z-index: 35 !important;
                        display: block !important;
                    }

                    .fi-sidebar-overlay:not(.fi-sidebar-open) {
                        display: none !important;
                    }

                    .fi-topbar-open-sidebar-btn {
                        z-index: 50 !important;
                        position: relative !important;
                    }
                }
                </style>
                CSS,
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
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => ! $isAuthenticatedAdminView()
                    ? ''
                    : Blade::render(<<<'HTML'
                    <style>
                    .feedback-widget-container, .feedback-btn { display: none !important; }
                    </style>
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
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function () use ($isAuthenticatedAdminView): string {
                    if ($isAuthenticatedAdminView()) return '';
                    return <<<'CSS'
                    <style>
                    body:has(.vx-login-hero){background:#f5f3ff!important}
                    .fi-simple-layout:has(.vx-login-hero){
                        position:relative!important;min-height:100vh!important;overflow:hidden!important;
                        background:radial-gradient(ellipse 80% 50% at 15% -10%,rgba(139,92,246,.28),transparent 60%),
                                   radial-gradient(ellipse 70% 50% at 100% 100%,rgba(99,102,241,.22),transparent 60%),
                                   linear-gradient(160deg,#f5f3ff 0%,#ede9fe 50%,#e0e7ff 100%)!important;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main-ctn{
                        position:relative!important;z-index:1!important;background:transparent!important;min-height:100vh!important;
                        display:flex!important;align-items:center;justify-content:center;padding:2rem 1.5rem;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-header{display:none!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main{
                        position:relative;
                        background:rgba(255,255,255,.85)!important;
                        backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);
                        border:1px solid rgba(255,255,255,.6)!important;
                        border-radius:1rem!important;
                        box-shadow:0 10px 40px rgba(76,29,149,.12)!important;
                        padding:2rem!important;
                        width:100%;max-width:26rem;
                        overflow:hidden;
                    }
                    /* Every nested wrapper Filament renders inside the card (sections,
                       field groups, etc.) brings its own background/border — strip all
                       of them so the glass card shows through uniformly, then
                       re-apply distinct styling to the actual interactive controls. */
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main *{
                        background-color:transparent!important;
                        background-image:none!important;
                        box-shadow:none!important;
                        border-color:transparent!important;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main,
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main *{
                        color:#4c1d95!important;
                    }
                    .vx-wave-banner{
                        margin:-2rem -2rem 1.5rem;padding:1.5rem 2rem;position:relative;overflow:hidden;
                        background:linear-gradient(135deg,#4c1d95 0%,#6d28d9 50%,#4f46e5 100%)!important;
                    }
                    .vx-wave-banner::before,.vx-wave-banner::after{
                        content:'';position:absolute;border-radius:50%;pointer-events:none;
                        background:rgba(255,255,255,.08);
                    }
                    .vx-wave-banner::before{width:140px;height:140px;top:-60px;right:-30px}
                    .vx-wave-banner::after{width:90px;height:90px;bottom:-50px;left:20%}
                    .vx-wave-banner-inner{position:relative;z-index:1;display:flex;align-items:center;gap:.6rem}
                    .vx-wave-banner-word{font-size:1.05rem;font-weight:800;color:#fff!important;letter-spacing:-.2px;line-height:1.1}
                    .vx-wave-banner-word span{font-weight:500;color:#ddd6fe!important;font-size:.85rem;margin-left:.35rem}
                    .vx-login-heading{margin-bottom:1.5rem}
                    .vx-login-heading h1{font-size:1.3rem;font-weight:700;color:#3b0764!important;margin:0 0 .3rem}
                    .vx-login-heading p{font-size:.85rem;color:#7c6f9c!important;margin:0}
                    .vx-login-footer{text-align:center;margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid rgba(124,58,237,.12)!important;font-size:.75rem;color:#9083b0!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main input{
                        background:#faf9fd!important;
                        border:1px solid rgba(124,58,237,.18)!important;
                        color:#3b0764!important;
                        border-radius:.6rem!important;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main input::placeholder{color:#b3a5cf!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main input:focus{
                        border-color:#8b5cf6!important;
                        box-shadow:0 0 0 3px rgba(139,92,246,.15)!important;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main svg{color:#8b5cf6!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main a{color:#7c3aed!important;text-decoration:none;font-weight:500}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main a:hover{color:#5b21b6!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main button[type="submit"]{
                        background:linear-gradient(135deg,#4a00e0 0%,#8e2de2 100%)!important;
                        border:none!important;
                        border-radius:.6rem!important;
                        box-shadow:0 4px 16px rgba(114,9,183,.35)!important;
                        color:#fff!important;
                        transition:transform .15s ease,box-shadow .15s ease;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main button[type="submit"]:hover{
                        transform:translateY(-1px);
                        box-shadow:0 6px 20px rgba(114,9,183,.45)!important;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main button[type="submit"] *{color:#fff!important}
                    </style>
                    CSS;
                },
            )
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
                QuickCreatePlugin::make()
                    ->excludes([
                        \App\Filament\Resources\PayoutResource::class,
                        \App\Filament\Resources\WeeklyPayoutBatchResource::class,
                        \App\Filament\Resources\ActivityLogResource::class,
                    ])
                    ->hidden(fn () => ! (auth()->user()?->isAdmin() || auth()->user()?->isOwner() || auth()->user()?->isStreamer())),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
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
