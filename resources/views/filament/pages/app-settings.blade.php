<x-filament-panels::page>
    <div class="space-y-6 max-w-3xl">

        {{-- ── Branding ──────────────────────────────────────────────────── --}}
        <div wire:key="section-branding" x-data="{ open: true }" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <div class="rounded-lg bg-gray-100 dark:bg-gray-800 p-2 shrink-0">
                    <x-heroicon-o-paint-brush class="h-5 w-5 text-gray-600 dark:text-gray-300" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Branding</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Logo, brand name, and primary color</p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200">
                    <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" />
                </span>
            </button>

            <div x-show="open" class="divide-y divide-gray-200 dark:divide-gray-700 border-t border-gray-200 dark:border-gray-700">

                {{-- Logo upload --}}
                <div class="px-6 py-4">
                    <label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Logo</label>
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-24 h-12 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 flex items-center justify-center overflow-hidden">
                            @if ($logo_path && file_exists(storage_path('app/public/' . $logo_path)))
                                <img src="{{ asset('storage/' . $logo_path) }}" alt="Logo" class="max-h-10 max-w-full object-contain" />
                            @else
                                <span class="text-xs font-bold text-gray-400">{{ $brand_name }}</span>
                            @endif
                        </div>
                        <div class="flex-1 space-y-2">
                            <input
                                wire:model="logo_upload"
                                type="file"
                                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 dark:file:bg-gray-700 dark:file:text-gray-300"
                            />
                            <p class="text-xs text-gray-400">PNG, JPG, SVG or WebP. Max 2 MB. Recommended: 160×40 px.</p>
                            @if ($logo_path)
                                <button wire:click="removeLogo" type="button" class="text-xs text-red-500 hover:text-red-600">Remove logo</button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Brand name --}}
                <div class="px-6 py-4">
                    <label for="brand_name" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">Brand Name</label>
                    <input
                        wire:model.blur="brand_name"
                        id="brand_name"
                        type="text"
                        maxlength="60"
                        placeholder="VortexOps"
                        class="w-full max-w-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none"
                    />
                    <p class="mt-1 text-xs text-gray-400">Shown in the sidebar header when no logo is set.</p>
                </div>

                {{-- Primary color --}}
                <div class="px-6 py-4">
                    <label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Primary Color</label>
                    <div class="flex items-center gap-3 flex-wrap">
                        @php
                            $presets = [
                                ['label' => 'Violet',   'hex' => '#7c3aed'],
                                ['label' => 'Blue',     'hex' => '#2563eb'],
                                ['label' => 'Indigo',   'hex' => '#4338ca'],
                                ['label' => 'Rose',     'hex' => '#e11d48'],
                                ['label' => 'Emerald',  'hex' => '#059669'],
                                ['label' => 'Amber',    'hex' => '#d97706'],
                                ['label' => 'Slate',    'hex' => '#475569'],
                                ['label' => 'Fuchsia',  'hex' => '#a21caf'],
                            ];
                        @endphp
                        @foreach ($presets as $preset)
                            <button
                                type="button"
                                wire:click="$set('primary_color', '{{ $preset['hex'] }}')"
                                title="{{ $preset['label'] }}"
                                class="w-7 h-7 rounded-full border-2 transition-transform hover:scale-110 {{ $primary_color === $preset['hex'] ? 'border-gray-800 dark:border-white scale-110 ring-2 ring-offset-1 ring-gray-400' : 'border-transparent' }}"
                                style="background-color: {{ $preset['hex'] }}"
                            ></button>
                        @endforeach
                        <div class="flex items-center gap-2 ml-2">
                            <input wire:model.live="primary_color" type="color"
                                class="w-8 h-8 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer bg-transparent p-0.5" title="Custom color" />
                            <input wire:model.live="primary_color" type="text" maxlength="7" placeholder="#7c3aed"
                                class="w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-2.5 py-1.5 text-xs font-mono text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-400">Applies to buttons, badges, active nav items, and accent elements. Reload after saving to see the change.</p>
                </div>

            </div>
        </div>

        @if ($this->canSeeModuleToggles)
        {{-- Demo / Showcase Mode --}}
        <div wire:key="section-demo-mode" x-data="{ open: true }" class="rounded-xl border {{ $demo_mode ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' }} overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-amber-50/50 dark:hover:bg-amber-950/20 transition-colors">
                <div class="rounded-lg bg-amber-100 dark:bg-amber-900/50 p-2 shrink-0">
                    <x-heroicon-o-presentation-chart-bar class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Demo / Showcase Mode</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        When on, non-admin visitors see a "Welcome to VortexOps" landing with blurred module previews.
                    </p>
                </div>
                {{-- Toggle — stop propagation so it doesn't collapse the card --}}
                <div x-data @click.stop class="shrink-0">
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="$wire.demo_mode.toString()"
                        @click="$wire.demo_mode = !$wire.demo_mode"
                        class="relative inline-flex h-8 w-16 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
                        :class="$wire.demo_mode ? 'bg-amber-500' : 'bg-gray-300 dark:bg-gray-600'"
                    >
                        <span
                            class="pointer-events-none inline-block h-6 w-6 rounded-full bg-white shadow transform ring-0 transition duration-200 ease-in-out"
                            :class="$wire.demo_mode ? 'translate-x-7' : 'translate-x-0.5'"
                        ></span>
                    </button>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="px-6 py-4 border-t border-amber-200 dark:border-amber-800/60">
                @if($demo_mode)
                    <div class="flex items-center gap-2 text-xs font-medium text-amber-700 dark:text-amber-400">
                        <x-heroicon-o-eye class="h-4 w-4" />
                        Demo mode is ACTIVE — non-admin users see the showcase landing page.
                    </div>
                @else
                    <p class="text-xs text-gray-400">Demo mode is off — all users see real data.</p>
                @endif
            </div>
        </div>

        {{-- Workspace Modules & Features --}}
        <div wire:key="section-modules" x-data="{ open: true }" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <div class="rounded-lg bg-slate-100 dark:bg-slate-800 p-2 shrink-0">
                    <x-heroicon-o-squares-2x2 class="h-5 w-5 text-slate-600 dark:text-slate-300" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Workspace Modules &amp; Features</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Toggle entire modules on/off, then fine-tune which individual pages are visible within each one.</p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 space-y-3">
                @if (auth()->user()?->isOwner())
                    <div class="flex flex-wrap items-center gap-2 pb-1">
                        <span class="text-xs font-medium text-gray-400 mr-1">Quick select:</span>
                        @foreach ($this->modulePresets as $key => $preset)
                            <button
                                type="button"
                                wire:click="applyModulePreset('{{ $key }}')"
                                title="{{ $preset['description'] }}"
                                class="inline-flex items-center rounded-full border border-gray-200 dark:border-gray-700 px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-300 hover:border-violet-400 hover:text-violet-600 dark:hover:text-violet-400 transition-colors"
                            >
                                {{ $preset['label'] }}
                            </button>
                        @endforeach
                        <span class="text-xs text-gray-400">— still requires Save Changes below.</span>
                    </div>
                @endif
                @foreach ($this->availableModules as $slug => $module)
                    @php
                        $moduleEnabled = in_array($slug, $enabled_modules);
                    @endphp
                    <div class="rounded-xl border {{ $moduleEnabled ? 'border-violet-200 dark:border-violet-800' : 'border-gray-200 dark:border-gray-700' }} overflow-hidden transition-colors">
                        <label class="flex items-start gap-3 px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <input
                                type="checkbox"
                                wire:model.live="enabled_modules"
                                value="{{ $slug }}"
                                class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 focus:ring-offset-0 bg-white dark:bg-gray-900"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $module['label'] }}</span>
                                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $module['group'] }}</span>
                                    @if (! $moduleEnabled)
                                        <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-[11px] font-medium text-gray-400 dark:text-gray-500">hidden</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $module['description'] }}</p>
                            </div>
                        </label>
                    </div>
                @endforeach
                <p class="text-xs text-gray-400 pt-1">Disabled modules disappear from navigation and their routes become inaccessible until re-enabled, for everyone including you. To control which individual pages a given role can see within an enabled module, use the Page Visibility checklist on each role in <strong>Roles &amp; Permissions</strong>. Changes apply on the next page load after saving.</p>
            </div>
        </div>
        @endif

        {{-- ── Show Import Settings ──────────────────────────────────────── --}}
        <div wire:key="section-show-import" x-data="{ open: true }" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <div class="rounded-lg bg-blue-100 dark:bg-blue-900 p-2 shrink-0">
                    <x-heroicon-o-video-camera class="h-5 w-5 text-blue-600 dark:text-blue-300" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Show Import</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Control how shows are ingested and streamers assigned</p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="divide-y divide-gray-200 dark:divide-gray-700 border-t border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <label for="show_import_mode" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">Import Mode</label>
                    <select wire:model.live="show_import_mode" id="show_import_mode"
                        class="w-full max-w-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none">
                        <option value="manual">Manual Entry</option>
                        <option value="auto_whatnot">Auto (Whatnot Scraper)</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-400">Manual: staff enter shows by hand. Auto: scraper ingests shows automatically.</p>
                </div>
                <div class="px-6 py-4">
                    <label for="show_ready_notification_email" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">Show-ready Notification Email</label>
                    <input wire:model.blur="show_ready_notification_email" id="show_ready_notification_email" type="email" placeholder="ops@yourcompany.com"
                        class="w-full max-w-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none" />
                    <p class="mt-1 text-xs text-gray-400">Optional. Receive an email when a show enters Pending Review.</p>
                </div>
            </div>
        </div>

        {{-- ── Notifications ────────────────────────────────────────────── --}}
        <div wire:key="section-notifications" x-data="{ open: false }" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <div class="rounded-lg bg-amber-100 dark:bg-amber-900 p-2 shrink-0">
                    <x-heroicon-o-bell class="h-5 w-5 text-amber-600 dark:text-amber-300" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Notifications</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Control who receives each type of in-app notification</p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="border-t border-gray-200 dark:border-gray-700">
                @php
                    $notifTypes = [
                        [
                            'key'         => 'low_stock',
                            'label'       => 'Low Stock Alert',
                            'description' => 'Sent when an item\'s total quantity falls at or below its reorder level.',
                            'icon'        => 'heroicon-o-exclamation-triangle',
                            'color'       => 'text-yellow-500',
                        ],
                        [
                            'key'         => 'damaged',
                            'label'       => 'Items Marked Damaged',
                            'description' => 'Sent immediately when units are moved to the damaged location.',
                            'icon'        => 'heroicon-o-fire',
                            'color'       => 'text-red-500',
                        ],
                        [
                            'key'         => 'show_ready',
                            'label'       => 'Show Ready for Review',
                            'description' => 'Sent when a new show is created and needs streamer assignment.',
                            'icon'        => 'heroicon-o-video-camera',
                            'color'       => 'text-blue-500',
                        ],
                        [
                            'key'         => 'show_reconciled',
                            'label'       => 'Show Reconciled',
                            'description' => 'Sent when a deduction request is approved and inventory is deducted. The show\'s streamers always receive this regardless of this setting.',
                            'icon'        => 'heroicon-o-check-circle',
                            'color'       => 'text-green-500',
                        ],
                    ];

                    $modeLabels = ['all' => 'All Users', 'admins' => 'Admins Only', 'custom' => 'Specific Users'];
                    $allUsers   = \App\Models\User::orderBy('name')->get();
                @endphp

                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($notifTypes as $notif)
                        @php
                            $modeKey  = 'notify_' . $notif['key'] . '_mode';
                            $usersKey = 'notify_' . $notif['key'] . '_users';
                        @endphp
                        <div class="px-6 py-4 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                <div class="flex items-start gap-3 flex-1">
                                    <div class="mt-0.5 shrink-0 {{ $notif['color'] }}">
                                        <x-dynamic-component :component="$notif['icon']" class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $notif['label'] }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $notif['description'] }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    <select
                                        wire:model.live="{{ $modeKey }}"
                                        class="rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-1.5 text-sm text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                                    >
                                        @foreach ($modeLabels as $val => $label)
                                            <option value="{{ $val }}" @selected($$modeKey === $val)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            @if ($$modeKey === 'custom')
                                <div class="ml-7 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-3">
                                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Select recipients</p>
                                    <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                        @forelse ($allUsers as $user)
                                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                                <input
                                                    type="checkbox"
                                                    wire:model.live="{{ $usersKey }}"
                                                    value="{{ $user->id }}"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 focus:ring-offset-0 bg-white dark:bg-gray-900"
                                                />
                                                <span class="text-sm text-gray-800 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white">
                                                    {{ $user->name }}
                                                </span>
                                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $user->email }}</span>
                                            </label>
                                        @empty
                                            <p class="text-xs text-gray-400">No users found.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Whatnot Import (owner only) ────────────────────────────────── --}}
        @if ($this->canSeeModuleToggles)
        <div wire:key="section-whatnot-import" x-data="{ open: true }" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <div class="rounded-lg bg-indigo-100 dark:bg-indigo-900/40 p-2 shrink-0">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Whatnot Show Import</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Scrapes completed shows from the Whatnot seller dashboard. Requires
                        <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">WHATNOT_EMAIL</code> and
                        <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">WHATNOT_PASSWORD</code> in <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">.env</code>.
                    </p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="divide-y divide-gray-200 dark:divide-gray-700 border-t border-gray-200 dark:border-gray-700">

                {{-- Credential status + test connection --}}
                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        @if ($this->whatnotConfigured)
                            <div class="flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                                <x-heroicon-o-check-circle class="h-4 w-4" />
                                Credentials configured
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                Use <strong>Test Connection</strong> to verify login works, then <strong>Run Import</strong> to pull shows.
                            </p>
                            @if ($whatnotLastImport)
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                                    Last import: {{ \Illuminate\Support\Carbon::parse($whatnotLastImport)->diffForHumans() }}
                                </p>
                            @endif
                        @else
                            <div class="flex items-center gap-1.5 text-sm text-amber-600 dark:text-amber-400">
                                <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
                                Credentials not set — add WHATNOT_EMAIL and WHATNOT_PASSWORD to .env
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            wire:click="testWhatnotConnection"
                            wire:loading.attr="disabled"
                            wire:target="testWhatnotConnection"
                            @if (! $this->whatnotConfigured) disabled @endif
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <span wire:loading.remove wire:target="testWhatnotConnection"><x-heroicon-o-signal class="h-4 w-4" /></span>
                            <span wire:loading wire:target="testWhatnotConnection">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </span>
                            <span wire:loading.remove wire:target="testWhatnotConnection">Test Connection</span>
                            <span wire:loading wire:target="testWhatnotConnection">Testing…</span>
                        </button>

                        <button
                            wire:click="importWhatnotShows"
                            wire:loading.attr="disabled"
                            wire:target="importWhatnotShows"
                            @if (! $this->whatnotConfigured) disabled @endif
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-indigo-300 dark:border-indigo-700 bg-indigo-50 dark:bg-indigo-950 px-3 py-2 text-sm font-medium text-indigo-700 dark:text-indigo-300 shadow-sm hover:bg-indigo-100 dark:hover:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <span wire:loading.remove wire:target="importWhatnotShows"><x-heroicon-o-arrow-down-tray class="h-4 w-4" /></span>
                            <span wire:loading wire:target="importWhatnotShows">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </span>
                            <span wire:loading.remove wire:target="importWhatnotShows">Run Import</span>
                            <span wire:loading wire:target="importWhatnotShows">Importing…</span>
                        </button>
                    </div>
                </div>

                @if ($whatnotTestResult)
                    <div class="px-6 py-3 {{ $whatnotTestStatus === 'success' ? 'bg-emerald-50 dark:bg-emerald-950' : 'bg-red-50 dark:bg-red-950' }}">
                        <div class="flex items-start gap-2">
                            @if ($whatnotTestStatus === 'success')
                                <x-heroicon-o-check-circle class="h-4 w-4 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" />
                            @else
                                <x-heroicon-o-x-circle class="h-4 w-4 text-red-600 dark:text-red-400 mt-0.5 shrink-0" />
                            @endif
                            <p class="text-xs font-mono {{ $whatnotTestStatus === 'success' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }} whitespace-pre-wrap">{{ $whatnotTestResult }}</p>
                        </div>
                    </div>
                @endif

                @if ($whatnotImportResult)
                    <div class="px-6 py-3 {{ $whatnotImportStatus === 'success' ? 'bg-emerald-50 dark:bg-emerald-950' : 'bg-red-50 dark:bg-red-950' }}">
                        <p class="text-xs font-mono {{ $whatnotImportStatus === 'success' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }} whitespace-pre-wrap">{{ $whatnotImportResult }}</p>
                    </div>
                @endif

            </div>
        </div>
        @endif

        {{-- ── Database Backup (owner only) ──────────────────────────────────── --}}
        @if ($this->canSeeModuleToggles)
        <div wire:key="section-db-backup" x-data="{ open: false }" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <div class="rounded-lg bg-teal-100 dark:bg-teal-900/40 p-2 shrink-0">
                    <x-heroicon-o-check-circle-stack class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Database Backup</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Dumps the database to <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">storage/app/backups/</code>.
                        Also runs automatically every night at 02:00.
                    </p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="divide-y divide-gray-200 dark:divide-gray-700 border-t border-gray-200 dark:border-gray-700">

                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Run Backup Now</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Creates a timestamped <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">.sql.gz</code> file and prunes copies older than 14 days.</p>
                    </div>
                    <button
                        wire:click="runBackup"
                        wire:loading.attr="disabled"
                        wire:target="runBackup"
                        type="button"
                        class="shrink-0 inline-flex items-center gap-2 rounded-lg border border-teal-300 dark:border-teal-700 bg-teal-50 dark:bg-teal-950 px-3 py-2 text-sm font-medium text-teal-700 dark:text-teal-300 shadow-sm hover:bg-teal-100 dark:hover:bg-teal-900 focus:outline-none focus:ring-2 focus:ring-teal-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span wire:loading.remove wire:target="runBackup"><x-heroicon-o-arrow-down-tray class="h-4 w-4" /></span>
                        <span wire:loading wire:target="runBackup">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="runBackup">Back Up Now</span>
                        <span wire:loading wire:target="runBackup">Backing up…</span>
                    </button>
                </div>

                @if ($backupResult)
                    <div class="px-6 py-3 {{ $backupStatus === 'success' ? 'bg-teal-50 dark:bg-teal-950' : 'bg-red-50 dark:bg-red-950' }}">
                        <p class="text-xs font-mono {{ $backupStatus === 'success' ? 'text-teal-700 dark:text-teal-300' : 'text-red-700 dark:text-red-300' }} whitespace-pre-wrap">{{ $backupResult }}</p>
                    </div>
                @endif

            </div>
        </div>
        @endif

        {{-- ── System & Maintenance (owner / super admin only) ─────────────── --}}
        @if ($this->canSeeModuleToggles)
        <div wire:key="section-system" x-data="{ open: false }" class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <div class="rounded-lg bg-gray-100 dark:bg-gray-800 p-2 shrink-0">
                    <x-heroicon-o-wrench-screwdriver class="h-5 w-5 text-gray-600 dark:text-gray-300" />
                </div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">System &amp; Maintenance</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Run migrations, optimize caches, and keep the app running at peak speed.</p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="divide-y divide-gray-200 dark:divide-gray-700 border-t border-gray-200 dark:border-gray-700">

                {{-- Run Migrations --}}
                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Run Pending Migrations</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Updates the database schema. Safe to run at any time — only applies outstanding changes.</p>
                    </div>
                    <button
                        wire:click="runMigrations"
                        wire:loading.attr="disabled"
                        wire:target="runMigrations"
                        type="button"
                        class="shrink-0 inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span wire:loading.remove wire:target="runMigrations"><x-heroicon-o-check-circle-stack class="h-4 w-4 text-gray-500 dark:text-gray-400" /></span>
                        <span wire:loading wire:target="runMigrations">
                            <svg class="h-4 w-4 animate-spin text-violet-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="runMigrations">Run Migrations</span>
                        <span wire:loading wire:target="runMigrations">Running…</span>
                    </button>
                </div>

                {{-- Optimize App --}}
                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Optimize Application</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Caches config, routes, views, and Filament components. Run this after every deployment for maximum speed.</p>
                    </div>
                    <button
                        wire:click="optimizeApp"
                        wire:loading.attr="disabled"
                        wire:target="optimizeApp"
                        type="button"
                        class="shrink-0 inline-flex items-center gap-2 rounded-lg border border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950 px-3 py-2 text-sm font-medium text-emerald-700 dark:text-emerald-300 shadow-sm hover:bg-emerald-100 dark:hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span wire:loading.remove wire:target="optimizeApp"><x-heroicon-o-bolt class="h-4 w-4" /></span>
                        <span wire:loading wire:target="optimizeApp">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="optimizeApp">Optimize</span>
                        <span wire:loading wire:target="optimizeApp">Optimizing…</span>
                    </button>
                </div>

                {{-- Clear Caches --}}
                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Clear All Caches</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Wipes config, route, view, and Filament component caches. Use when changes aren't reflecting after deployment.</p>
                    </div>
                    <button
                        wire:click="clearCaches"
                        wire:loading.attr="disabled"
                        wire:target="clearCaches"
                        type="button"
                        class="shrink-0 inline-flex items-center gap-2 rounded-lg border border-rose-300 dark:border-rose-700 bg-rose-50 dark:bg-rose-950 px-3 py-2 text-sm font-medium text-rose-700 dark:text-rose-300 shadow-sm hover:bg-rose-100 dark:hover:bg-rose-900 focus:outline-none focus:ring-2 focus:ring-rose-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span wire:loading.remove wire:target="clearCaches"><x-heroicon-o-trash class="h-4 w-4" /></span>
                        <span wire:loading wire:target="clearCaches">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="clearCaches">Clear Caches</span>
                        <span wire:loading wire:target="clearCaches">Clearing…</span>
                    </button>
                </div>

                {{-- Clear Demo Data (owner only) --}}
                @if (auth()->user()?->isOwner())
                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Clear Demo Data</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Permanently removes all shows, inventory, pallets, payouts, and related records — real data included, not just seeded demo rows. Users, streamers, vendors, channels, and settings are kept.</p>
                    </div>
                    <div class="shrink-0">
                        {{ $this->clearDemoDataAction }}
                    </div>
                </div>
                @endif

                {{-- Last command output --}}
                @if ($lastCommandOutput)
                    <div class="px-6 py-4 bg-gray-950">
                        <p class="text-xs font-medium text-gray-400 mb-2 flex items-center gap-1.5">
                            <x-heroicon-o-command-line class="h-3.5 w-3.5" /> Last command output
                        </p>
                        <pre class="text-xs text-emerald-400 font-mono whitespace-pre-wrap leading-relaxed">{{ $lastCommandOutput }}</pre>
                    </div>
                @endif

            </div>
        </div>
        @endif

        {{-- ── AI / Ollama (owner / super admin only) ───────────────────────── --}}
        @if ($this->canSeeModuleToggles)
        <div wire:key="section-ai-ollama" x-data="{ open: false }" class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <x-heroicon-o-sparkles class="h-5 w-5 text-primary-500 shrink-0" />
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">AI / Ollama</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Chat model, vision model, embedding model, and connection settings</p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="divide-y divide-gray-100 dark:divide-gray-700 border-t border-gray-100 dark:border-gray-700">

                {{-- Provider --}}
                <div class="px-6 py-4">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">AI Provider</label>
                    <select wire:model.live="ai_provider"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        <option value="ollama">Ollama (local / self-hosted)</option>
                        <option value="openai">OpenAI-compatible (OpenAI, DeepSeek, Qwen, …)</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Which backend the AI gateway uses. The model names below apply to whichever provider is active.
                        @if ($ai_provider === 'openai')
                            <span class="block mt-1">Set <code class="font-mono">OPENAI_BASE_URL</code> and <code class="font-mono">OPENAI_API_KEY</code> in your environment (keys are not stored in the database).</span>
                        @endif
                    </p>
                </div>

                {{-- Ollama Base URL --}}
                <div class="px-6 py-4">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Ollama Base URL</label>
                    <input
                        wire:model.blur="ollama_base_url"
                        type="url"
                        placeholder="http://localhost:11434"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">URL of your Ollama server (default: http://localhost:11434)</p>
                </div>

                {{-- Model selectors — one model per AI task, resolved by the ModelRouter --}}
                <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Chat Model</label>
                        <input wire:model.blur="ollama_chat_model" type="text" placeholder="llama3.2:3b"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Conversational assistant + mapping LLM stage (e.g. qwen3:4b)</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Fast Model</label>
                        <input wire:model.blur="ollama_fast_model" type="text" placeholder="llama3.2:1b"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Quick, low-stakes calls — classification, short rewrites</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Reasoning Model</label>
                        <input wire:model.blur="ollama_reasoning_model" type="text" placeholder="llama3.2:3b"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Multi-step analysis where quality beats speed</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Vision Model</label>
                        <input wire:model.blur="ollama_vision_model" type="text" placeholder="moondream"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Packing-slip / screenshot parsing (e.g. qwen2.5-vl, moondream)</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Embedding Model</label>
                        <input wire:model.blur="ollama_embedding_model" type="text" placeholder="nomic-embed-text"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Inventory similarity search (e.g. nomic-embed-text)</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">JSON Model</label>
                        <input wire:model.blur="ollama_json_model" type="text" placeholder="llama3.2:3b"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Structured extraction — must return valid JSON</p>
                    </div>
                </div>

                {{-- Generation defaults --}}
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Temperature</label>
                        <input wire:model.blur="ai_temperature" type="number" step="0.1" min="0" max="2" placeholder="0.7"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">0 = deterministic, 2 = most creative</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Max Tokens</label>
                        <input wire:model.blur="ai_max_tokens" type="number" step="1" min="1" placeholder="1024"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Cap on response length per call</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Streaming</label>
                        <label class="mt-1 inline-flex items-center cursor-pointer">
                            <input wire:model.live="ai_streaming" type="checkbox" class="sr-only peer">
                            <div class="relative w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:ring-2 peer-focus:ring-violet-500 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-600"></div>
                            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">Stream chat responses token-by-token</span>
                        </label>
                    </div>
                </div>

                {{-- Test Connection --}}
                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Test Connection</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Verify Ollama is reachable and list available models.</p>
                    </div>
                    <button
                        wire:click="testOllamaConnection"
                        wire:loading.attr="disabled"
                        wire:target="testOllamaConnection"
                        type="button"
                        class="shrink-0 inline-flex items-center gap-2 rounded-lg border border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 px-3 py-2 text-sm font-medium text-violet-700 dark:text-violet-300 shadow-sm hover:bg-violet-100 dark:hover:bg-violet-900 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span wire:loading.remove wire:target="testOllamaConnection"><x-heroicon-o-signal class="h-4 w-4" /></span>
                        <span wire:loading wire:target="testOllamaConnection">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="testOllamaConnection">Test Connection</span>
                        <span wire:loading wire:target="testOllamaConnection">Testing…</span>
                    </button>
                </div>

                @if ($ollamaTestResult)
                    <div class="px-6 py-3 {{ $ollamaTestStatus === 'success' ? 'bg-emerald-50 dark:bg-emerald-950' : 'bg-red-50 dark:bg-red-950' }}">
                        <p class="text-xs {{ $ollamaTestStatus === 'success' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">
                            {{ $ollamaTestResult }}
                        </p>
                    </div>
                @endif

                {{-- Product embeddings (enables semantic stage-3 matching) --}}
                @php $cov = $this->embeddingCoverage; @endphp
                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-800">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Product Embeddings</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Powers semantic matching on the receiving flow.
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $cov['embedded'] }} of {{ $cov['total'] }}</span> active products embedded.
                            Runs synchronously against Ollama — no queue worker needed.
                        </p>
                    </div>
                    <button
                        wire:click="generateEmbeddingsNow"
                        wire:loading.attr="disabled"
                        wire:target="generateEmbeddingsNow"
                        type="button"
                        class="shrink-0 inline-flex items-center gap-2 rounded-lg border border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 px-3 py-2 text-sm font-medium text-violet-700 dark:text-violet-300 shadow-sm hover:bg-violet-100 dark:hover:bg-violet-900 focus:outline-none focus:ring-2 focus:ring-violet-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span wire:loading.remove wire:target="generateEmbeddingsNow"><x-heroicon-o-sparkles class="h-4 w-4" /></span>
                        <span wire:loading wire:target="generateEmbeddingsNow">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="generateEmbeddingsNow">Generate embeddings</span>
                        <span wire:loading wire:target="generateEmbeddingsNow">Generating…</span>
                    </button>
                </div>

            </div>
        </div>
        @endif

        {{-- ── Shipping Surcharge ──────────────────────────────────────────── --}}
        <div wire:key="section-shipping" x-data="{ open: false }" class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <x-heroicon-o-truck class="h-5 w-5 text-amber-500 shrink-0" />
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Shipping Surcharge</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Per-package surcharge rate and threshold amount</p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Rate per Package ($)</label>
                    <input wire:model.blur="shipping_surcharge_rate" type="number" step="0.01" min="0" placeholder="4.00"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Charge per package over the threshold (default $4.00)</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Threshold Amount ($)</label>
                    <input wire:model.blur="shipping_surcharge_threshold" type="number" step="0.01" min="0" placeholder="500.00"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Package value threshold that triggers the surcharge (default $500.00)</p>
                </div>
            </div>
        </div>

        {{-- ── Vortex Fee (default) ────────────────────────────────────────── --}}
        <div wire:key="section-owner-fee" x-data="{ open: false }" class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">

            <button type="button" @click="open = !open"
                class="w-full px-6 py-4 flex items-center gap-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <x-heroicon-o-banknotes class="h-5 w-5 text-emerald-500 shrink-0" />
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Vortex Fee</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Default fee applied to a streamer's payout — used unless that streamer has their own override set</p>
                </div>
                <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200"><x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" /></span>
            </button>

            <div x-show="open" class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 space-y-4">
                <p class="text-xs text-gray-500 dark:text-gray-400">Applies to every streamer who doesn't have their own fee type set on their profile (Streamers → edit → Vortex Fee section). A streamer-level override always wins over this default.</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Fee Type</label>
                        <select wire:model.live="default_owner_fee_type"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                            <option value="">No default fee</option>
                            <option value="percentage">Percentage (%)</option>
                            <option value="flat">Flat Amount ($)</option>
                        </select>
                    </div>
                    @if ($default_owner_fee_type)
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $default_owner_fee_type === 'flat' ? 'Fee Amount ($)' : 'Fee Percentage (%)' }}</label>
                            <input wire:model.blur="default_owner_fee_value" type="number" step="0.01" min="0" placeholder="0.00"
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                        </div>
                        <div class="flex items-end pb-2">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input wire:model="default_owner_fee_deduct_from_payout" type="checkbox"
                                    class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm text-gray-700 dark:text-gray-300">Deduct from payout</span>
                            </label>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Validation errors --}}
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 px-4 py-3">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li class="text-sm text-red-700 dark:text-red-300">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

    </div>
</x-filament-panels::page>
