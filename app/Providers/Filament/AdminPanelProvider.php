
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
                <div class="vx-login-showcase" aria-hidden="true">
                    <div class="vx-login-showcase-inner">
                        <div class="vx-login-brand">
                            <img src="/images/vb-logo-sidebar.svg" alt="">
                        </div>
                        <div class="vx-login-eyebrow">STREAMS <span>•</span> INVENTORY <span>•</span> FULFILLMENT <span>•</span> PAYROLL</div>
                        <h2>Manage Your<br><strong>Whatnot Operations</strong></h2>
                        <p>Track shows, manage inventory, handle fulfillment, and streamline payroll — all in one place.</p>
                        <div class="vx-login-features">
                            <div><b>▣</b><span><strong>Show Management</strong><small>Track and analyze your live shows</small></span></div>
                            <div><b>◇</b><span><strong>Inventory Control</strong><small>Keep your stock synced and updated</small></span></div>
                            <div><b>▰</b><span><strong>Fulfillment</strong><small>Manage orders and shipments</small></span></div>
                            <div><b>▥</b><span><strong>Reports & Analytics</strong><small>Get insights and grow your business</small></span></div>
                        </div>
                        <div class="vx-login-preview">
                            <div class="vx-preview-bar"><span>VORTEX <em>OPS</em></span><i></i></div>
                            <div class="vx-preview-title">Performance Overview</div>
                            <div class="vx-preview-cards">
                                <div><small>Total Sales</small><strong>$447,693</strong><span>↗ 12%</span></div>
                                <div><small>Orders</small><strong>17,130</strong><span>↗ 8%</span></div>
                                <div><small>Avg Order Value</small><strong>$26.14</strong><span>↗ 5%</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="vx-login-hero">
                    <div class="vx-login-card-brand">
                        <img src="/images/vb-logo-sidebar.svg" alt="Vortex Ops">
                    </div>
                    <div class="vx-login-heading">
                        <h1>Welcome back</h1>
                        <p>Sign in to your account to manage shows, inventory, fulfillment, and payroll.</p>
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
     * Precedence: the active channel's own title, else the global brand name —
     * except when falling back to the built-in SVG logo (no channel logo, no
     * global logo), which already contains the wordmark so the text is hidden.
     */
    public static function resolveBrandName(?WhatnotChannel $channel, string $brandName, ?string $logoPath): string
    {
        if ($channel?->display_title) {
            return $channel->display_title;
        }
