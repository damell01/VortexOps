<?php

namespace App\Providers\Filament;

use App\Models\Setting;
use Awcodes\QuickCreate\QuickCreatePlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Support\AdminModules;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use App\Filament\Pages\Dashboard;
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

        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile(isSimple: false)
            ->brandName($brandName)
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarFullyCollapsibleOnDesktop()
            ->maxContentWidth(\Filament\Support\Enums\Width::SevenExtraLarge)
            ->globalSearchKeyBindings(['mod+k'])
            ->globalSearchDebounce('300ms')
            ->colors([
                'primary' => Color::hex($primaryColor),
                'gray'    => Color::Zinc,
                'info'    => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger'  => Color::Rose,
            ]);

        if ($logoPath && file_exists(storage_path('app/public/' . $logoPath))) {
            $panel = $panel
                ->brandLogo(asset('storage/' . $logoPath))
                ->brandLogoHeight('2.75rem');
        } elseif (file_exists(public_path('images/vb-logo-sidebar.svg'))) {
            // Default to the built-in SVG logo when no custom logo is uploaded
            $panel = $panel
                ->brandLogo(asset('images/vb-logo-sidebar.svg'))
                ->brandLogoHeight('2.75rem')
                ->brandName('');   // text hidden — logo SVG already contains it
        }

        $isAuthenticatedAdminView = fn (): bool => auth()->check();
        $hasViteManifest = fn (): bool => file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));

        $pwaIconsExist = fn (): bool => file_exists(public_path('icons/icon-192.png'));

        return $panel
            ->spa(hasPrefetching: true)
            ->databaseNotifications()
            ->databaseNotificationsPolling('300s')
            ->navigationGroups(array_map(
                fn (string $group): NavigationGroup => $group === 'Settings'
                    ? NavigationGroup::make($group)->collapsed()
                    : NavigationGroup::make($group),
                AdminModules::visibleNavigationGroups(),
            ))
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => ! $hasViteManifest()
                    ? ''
                    : ($isAuthenticatedAdminView()
                        ? Blade::render("@vite(['resources/css/app.css', 'resources/js/app.js'])")
                        : Blade::render("@vite(['resources/css/app.css'])")),
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
                    : Blade::render(
                        "<x-tour-button />"
                        . "@livewire('feedback-widget')"
                        . "@livewire('ai-chat-panel')"
                    ),
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
            // ── Login page: inject split-screen CSS ──────────────────────────────
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function () use ($isAuthenticatedAdminView): string {
                    if ($isAuthenticatedAdminView()) return '';
                    return <<<'CSS'
                    <style>
                    .vx-login-brand{display:none;position:relative;overflow:hidden;background:linear-gradient(155deg,#1a0535 0%,#3b0e72 45%,#6d28d9 100%);flex-direction:column;justify-content:center;flex-shrink:0}
                    .vx-lb-bubble{position:absolute;border-radius:50%;pointer-events:none}
                    .vx-lb-bubble-1{width:340px;height:340px;top:-110px;right:-110px;background:rgba(124,58,237,.2)}
                    .vx-lb-bubble-2{width:200px;height:200px;bottom:-60px;left:-60px;background:rgba(124,58,237,.15)}
                    .vx-lb-bubble-3{width:110px;height:110px;bottom:28%;right:30px;background:rgba(167,139,250,.12)}
                    @media(max-width:899px){
                        .vx-login-brand{display:flex;flex-direction:row;align-items:center;gap:1rem;padding:1.25rem 1.5rem}
                        .vx-lb-tagline,.vx-lb-features,.vx-lb-footer{display:none!important}
                        .vx-lb-bubble{display:none}
                    }
                    @media(min-width:900px){
                        .vx-login-brand{display:flex;width:420px;padding:3rem 2.5rem}
                        .fi-simple-layout:has(.vx-login-brand){display:flex!important;flex-direction:row!important;min-height:100vh}
                        .fi-simple-layout:has(.vx-login-brand) .fi-simple-main-ctn{flex:1;display:flex;align-items:center;justify-content:center;padding:2rem;background:#f5f3ff}
                    }
                    .fi-simple-layout:has(.vx-login-brand) .fi-simple-header{display:none!important}
                    .fi-simple-layout:has(.vx-login-brand) .fi-simple-main{background:#fff;border-radius:1.25rem;box-shadow:0 4px 40px rgba(109,40,217,.12),0 1px 4px rgba(0,0,0,.06);padding:2.5rem!important;width:100%}
                    .vx-form-heading{text-align:center;margin-bottom:1.75rem}
                    .vx-form-heading h2{font-size:1.4rem;font-weight:700;color:#111827;margin:0 0 .3rem}
                    .vx-form-heading p{font-size:.85rem;color:#6b7280;margin:0}
                    @media(prefers-color-scheme:dark){
                        .fi-simple-layout:has(.vx-login-brand) .fi-simple-main-ctn{background:#0f0a1e}
                        .fi-simple-layout:has(.vx-login-brand) .fi-simple-main{background:#1e1b2e;box-shadow:0 4px 40px rgba(0,0,0,.4)}
                        .vx-form-heading h2{color:#f1f5f9}
                        .vx-form-heading p{color:#94a3b8}
                    }
                    [data-theme=dark] .fi-simple-layout:has(.vx-login-brand) .fi-simple-main-ctn{background:#0f0a1e}
                    [data-theme=dark] .fi-simple-layout:has(.vx-login-brand) .fi-simple-main{background:#1e1b2e;box-shadow:0 4px 40px rgba(0,0,0,.4)}
                    [data-theme=dark] .vx-form-heading h2{color:#f1f5f9}
                    [data-theme=dark] .vx-form-heading p{color:#94a3b8}
                    </style>
                    CSS;
                },
            )
            // ── Login page: inject brand panel (left side) ───────────────────────
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                function () use ($isAuthenticatedAdminView): string {
                    if ($isAuthenticatedAdminView()) return '';
                    return <<<'HTML'
                    <div class="vx-login-brand">
                        <div class="vx-lb-bubble vx-lb-bubble-1"></div>
                        <div class="vx-lb-bubble vx-lb-bubble-2"></div>
                        <div class="vx-lb-bubble vx-lb-bubble-3"></div>
                        <div style="position:relative;z-index:1;display:flex;flex-direction:column;height:100%">
                            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:auto">
                                <svg viewBox="0 0 100 100" width="54" height="54" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">
                                    <defs><mask id="vx-lm"><rect width="100" height="100" fill="white"/><rect x="0" y="19.5" width="100" height="9" fill="black"/></mask></defs>
                                    <path mask="url(#vx-lm)" d="M 23,15 L 77,15 Q 87,15 82,25 L 53,80 Q 50,87 47,80 L 18,25 Q 13,15 23,15 Z" stroke="#f0ece6" stroke-width="5.5" stroke-linejoin="round" fill="none"/>
                                    <path d="M 30,24 L 70,24 Q 79,24 74.5,32 L 52.5,75 Q 50,81 47.5,75 L 25.5,32 Q 21,24 30,24 Z" stroke="#f0ece6" stroke-width="5" stroke-linejoin="round" fill="none"/>
                                    <path d="M 23,15 L 77,15" stroke="#f0ece6" stroke-width="5.5" stroke-linecap="round"/>
                                    <path d="M 30,24 L 70,24" stroke="#f0ece6" stroke-width="5" stroke-linecap="round"/>
                                </svg>
                                <div>
                                    <div style="font-size:1.5rem;font-weight:800;color:#fff;letter-spacing:-.5px;line-height:1">VORTEX</div>
                                    <div style="font-size:.7rem;font-weight:700;color:#c4b5fd;letter-spacing:4px;line-height:1.6">BREAKS</div>
                                </div>
                            </div>
                            <div class="vx-lb-tagline" style="padding:2.5rem 0">
                                <div style="font-size:1.35rem;font-weight:700;color:#fff;margin-bottom:.6rem;line-height:1.25">Operations<br>Platform</div>
                                <div style="color:#c4b5fd;font-size:.83rem;line-height:1.65">Your all-in-one hub for show management, inventory tracking, and streamer payouts.</div>
                            </div>
                            <div class="vx-lb-features" style="display:flex;flex-direction:column;gap:.9rem">
                                <div style="display:flex;align-items:center;gap:.75rem"><div style="width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">🎬</div><span style="color:#e9d5ff;font-size:.825rem">Show tracking &amp; reconciliation</span></div>
                                <div style="display:flex;align-items:center;gap:.75rem"><div style="width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">📦</div><span style="color:#e9d5ff;font-size:.825rem">Inventory management</span></div>
                                <div style="display:flex;align-items:center;gap:.75rem"><div style="width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">💰</div><span style="color:#e9d5ff;font-size:.825rem">Streamer payouts</span></div>
                                <div style="display:flex;align-items:center;gap:.75rem"><div style="width:30px;height:30px;border-radius:7px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">✨</div><span style="color:#e9d5ff;font-size:.825rem">AI-powered assistant</span></div>
                            </div>
                            <div class="vx-lb-footer" style="margin-top:2rem;padding-top:1.25rem;border-top:1px solid rgba(255,255,255,.1)">
                                <div style="color:rgba(255,255,255,.3);font-size:.72rem">Built by DBell Creations</div>
                            </div>
                        </div>
                    </div>
                    HTML;
                },
            )
            // ── Login page: "Welcome back" heading above the form ────────────────
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => <<<'HTML'
                <div class="vx-form-heading">
                    <h2>Welcome back</h2>
                    <p>Sign in to your VortexOps account</p>
                </div>
                HTML,
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
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
                    ->hidden(fn () => ! (auth()->user()?->isAdmin())),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
