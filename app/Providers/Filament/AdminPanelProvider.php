<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Models\Setting;
use App\Support\AdminModules;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
use Illuminate\Support\Js;
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

        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
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
        }

        $isAuthenticatedAdminView = fn (): bool => auth()->check();
        $hasViteManifest = fn (): bool => file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'));

        try {
            $navigationGroups = AdminModules::visibleNavigationGroups();
        } catch (\Throwable) {
            $navigationGroups = ['Settings'];
        }

        try {
            $moduleFlags = [
                'projects' => AdminModules::isEnabled('projects'),
                'reviews'  => AdminModules::isEnabled('reviews'),
                'ai'       => AdminModules::isEnabled('ai'),
            ];
        } catch (\Throwable) {
            $moduleFlags = [
                'projects' => true,
                'reviews'  => true,
                'ai'       => false,
            ];
        }

        return $panel
            ->spa(hasPrefetching: false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('300s')
            ->navigationGroups(array_map(
                fn (string $group): NavigationGroup => $group === 'Settings'
                    ? NavigationGroup::make($group)->collapsed()
                    : NavigationGroup::make($group),
                $navigationGroups,
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
                PanelsRenderHook::BODY_END,
                fn (): string => ! $isAuthenticatedAdminView()
                    ? ''
                    : Blade::render(
                        '<script>window.VortexModules = ' . Js::from([
                            'projects' => $moduleFlags['projects'],
                            'reviews'  => $moduleFlags['reviews'],
                            'ai'       => $moduleFlags['ai'],
                        ]) . ';</script>' .
                        ($moduleFlags['ai']
                            ? '
                                <div x-data="{ aiPanelLoaded: false }">
                                    <button
                                        x-cloak
                                        x-show="! aiPanelLoaded"
                                        @click="aiPanelLoaded = true"
                                        class="fixed bottom-24 right-6 z-[99999] rounded-full bg-violet-600 px-4 py-3 text-sm font-semibold text-white shadow-xl transition hover:scale-105 hover:bg-violet-700"
                                        title="Open Vortex Assistant"
                                    >
                                        Assistant
                                    </button>

                                    <div x-cloak x-show="aiPanelLoaded">
                                        <livewire:ai-chat-panel lazy :initially-open="true" />
                                    </div>
                                </div>
                            '
                            : '')
                        . "<x-tour-button />"
                    ),
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
