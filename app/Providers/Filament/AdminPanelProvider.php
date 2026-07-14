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
            ->maxContentWidth(\Filament\Support\Enums\Width::Full)
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
                // Collapse every group except the primary "Streams" workflow, so the
                // sidebar stays compact — you expand the group you need.
                fn (string $group): NavigationGroup => $group === 'Streams'
                    ? NavigationGroup::make($group)
                    : NavigationGroup::make($group)->collapsed(),
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
                    : Blade::render("@livewire('feedback-widget')"),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => ! $isAuthenticatedAdminView()
                    ? ''
                    : view('filament.components.camera-barcode-scanner'),
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
            // ── Login page: full-bleed gradient background + centered glass card ─
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function () use ($isAuthenticatedAdminView): string {
                    if ($isAuthenticatedAdminView()) return '';
                    return <<<'CSS'
                    <style>
                    .fi-simple-layout:has(.vx-login-hero){
                        position:relative;min-height:100vh;overflow:hidden;
                        background:radial-gradient(ellipse 90% 60% at 15% 0%,rgba(109,40,217,.5),transparent 60%),
                                   radial-gradient(ellipse 80% 60% at 100% 100%,rgba(124,58,237,.4),transparent 60%),
                                   linear-gradient(155deg,#150430 0%,#330d6b 45%,#5b21b6 100%);
                    }
                    .fi-simple-layout:has(.vx-login-hero)::before{
                        content:'';position:absolute;inset:0;pointer-events:none;opacity:.5;
                        background-image:
                            repeating-linear-gradient(115deg,transparent 0 60px,rgba(196,181,253,.05) 60px 62px),
                            repeating-linear-gradient(95deg,transparent 0 90px,rgba(196,181,253,.04) 90px 92px);
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main-ctn{
                        position:relative;z-index:1;background:transparent!important;min-height:100vh;
                        display:flex;align-items:center;justify-content:center;padding:2rem 1.5rem;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-header{display:none!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main{
                        background:rgba(30,16,56,.55)!important;
                        backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
                        border:1px solid rgba(255,255,255,.1);
                        border-radius:1.25rem;
                        box-shadow:0 20px 60px rgba(0,0,0,.35);
                        padding:2.25rem 2.25rem 1.75rem!important;
                        width:100%;max-width:26rem;
                    }
                    .vx-login-hero{position:relative;z-index:1;text-align:center;margin:0 auto 1.75rem;max-width:26rem}
                    .vx-login-hero-mark{display:flex;align-items:center;justify-content:center;gap:.65rem;margin-bottom:1.25rem}
                    .vx-login-hero-word{font-size:1.35rem;font-weight:800;color:#fff;letter-spacing:-.3px;line-height:1;text-align:left}
                    .vx-login-hero-word small{display:block;font-size:.62rem;font-weight:700;color:#c4b5fd;letter-spacing:3px;line-height:1.7}
                    .vx-login-hero h1{font-size:1.35rem;font-weight:700;color:#fff;margin:0}
                    .vx-form-heading{text-align:center;margin-bottom:1.5rem}
                    .vx-form-heading p{font-size:.85rem;color:#c4b5fd;margin:0}
                    .vx-login-footer{position:relative;z-index:1;text-align:center;margin-top:1.5rem;font-size:.75rem;color:rgba(255,255,255,.35)}
                    /* Dark-glass form controls */
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main label{color:#e9d5ff!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main input{
                        background:rgba(255,255,255,.06)!important;
                        border-color:rgba(255,255,255,.14)!important;
                        color:#fff!important;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main input::placeholder{color:rgba(255,255,255,.35)!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main input:focus{
                        border-color:#a78bfa!important;
                        box-shadow:0 0 0 1px #a78bfa!important;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main .fi-fo-field-wrp-error-message,
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main .fi-fo-field-wrp-hint{color:#c4b5fd!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main a{color:#c4b5fd!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main a:hover{color:#e9d5ff!important}
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main button[type="submit"],
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main .fi-btn[type="submit"]{
                        background:linear-gradient(90deg,#7c3aed,#a78bfa)!important;
                        border:none!important;
                        box-shadow:0 4px 16px rgba(124,58,237,.4)!important;
                    }
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main button[type="submit"]:hover,
                    .fi-simple-layout:has(.vx-login-hero) .fi-simple-main .fi-btn[type="submit"]:hover{
                        background:linear-gradient(90deg,#6d28d9,#8b5cf6)!important;
                    }
                    </style>
                    CSS;
                },
            )
            // ── Login page: logo + heading above the card ────────────────────────
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                function () use ($isAuthenticatedAdminView): string {
                    if ($isAuthenticatedAdminView()) return '';
                    return <<<'HTML'
                    <div class="vx-login-hero">
                        <div class="vx-login-hero-mark">
                            <svg viewBox="0 0 100 100" width="34" height="34" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">
                                <defs><mask id="vx-lm2"><rect width="100" height="100" fill="white"/><rect x="0" y="19.5" width="100" height="9" fill="black"/></mask></defs>
                                <path mask="url(#vx-lm2)" d="M 23,15 L 77,15 Q 87,15 82,25 L 53,80 Q 50,87 47,80 L 18,25 Q 13,15 23,15 Z" stroke="#f0ece6" stroke-width="5.5" stroke-linejoin="round" fill="none"/>
                                <path d="M 30,24 L 70,24 Q 79,24 74.5,32 L 52.5,75 Q 50,81 47.5,75 L 25.5,32 Q 21,24 30,24 Z" stroke="#f0ece6" stroke-width="5" stroke-linejoin="round" fill="none"/>
                                <path d="M 23,15 L 77,15" stroke="#f0ece6" stroke-width="5.5" stroke-linecap="round"/>
                                <path d="M 30,24 L 70,24" stroke="#f0ece6" stroke-width="5" stroke-linecap="round"/>
                            </svg>
                            <div class="vx-login-hero-word">VORTEX<small>BREAKS</small></div>
                        </div>
                        <h1>Operations Platform &mdash; Welcome Back</h1>
                    </div>
                    HTML;
                },
            )
            // ── Login page: subheading inside the card, above the form ───────────
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => <<<'HTML'
                <div class="vx-form-heading">
                    <p>Sign in to manage your hub</p>
                </div>
                HTML,
            )
            // ── Login page: footer credit below the card ─────────────────────────
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_END,
                function () use ($isAuthenticatedAdminView): string {
                    if ($isAuthenticatedAdminView()) return '';
                    return <<<'HTML'
                    <div class="vx-login-footer">Built by DBell Creations</div>
                    HTML;
                },
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
