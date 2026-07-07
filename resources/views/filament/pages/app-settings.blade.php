<x-filament-panels::page>
    <div class="space-y-6 max-w-3xl">

        {{-- ── Branding ──────────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">

            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-gray-100 dark:bg-gray-800 p-2">
                        <x-heroicon-o-paint-brush class="h-5 w-5 text-gray-600 dark:text-gray-300" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Branding</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Logo, brand name, and primary color</p>
                    </div>
                </div>
            </div>

            {{-- Logo upload --}}
            <div class="px-6 py-4">
                <label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Logo</label>
                <div class="flex items-start gap-4">
                    {{-- Current logo preview --}}
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

                    {{-- Preset swatches --}}
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

                    {{-- Custom hex picker --}}
                    <div class="flex items-center gap-2 ml-2">
                        <input
                            wire:model.live="primary_color"
                            type="color"
                            class="w-8 h-8 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer bg-transparent p-0.5"
                            title="Custom color"
                        />
                        <input
                            wire:model.live="primary_color"
                            type="text"
                            maxlength="7"
                            placeholder="#7c3aed"
                            class="w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-2.5 py-1.5 text-xs font-mono text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-400">Applies to buttons, badges, active nav items, and accent elements. Reload after saving to see the change.</p>
            </div>

        </div>

        @if ($this->canSeeModuleToggles)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-slate-100 dark:bg-slate-800 p-2">
                        <x-heroicon-o-squares-2x2 class="h-5 w-5 text-slate-600 dark:text-slate-300" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Workspace Modules &amp; Features</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Toggle entire modules on/off, then fine-tune which individual pages are visible within each one.</p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 space-y-3">
                @foreach ($this->availableModules as $slug => $module)
                    @php
                        $moduleEnabled = in_array($slug, $enabled_modules);
                        $features      = \App\Support\AdminModules::featuresForModule($slug);
                        uasort($features, fn($a, $b) => $a['order'] <=> $b['order']);
                    @endphp

                    <div class="rounded-xl border {{ $moduleEnabled ? 'border-violet-200 dark:border-violet-800' : 'border-gray-200 dark:border-gray-700' }} overflow-hidden transition-colors">

                        {{-- Module header row --}}
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

                        {{-- Feature rows (shown when module has features) --}}
                        @if (count($features))
                            <div class="{{ $moduleEnabled ? '' : 'opacity-40 pointer-events-none' }} border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 px-4 py-2 space-y-1">
                                <p class="text-[11px] font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-2">Pages &amp; features</p>
                                @foreach ($features as $featureSlug => $feature)
                                    <label class="flex items-start gap-2.5 py-1 cursor-pointer group">
                                        <input
                                            type="checkbox"
                                            wire:model.live="enabled_features"
                                            value="{{ $featureSlug }}"
                                            {{ ! $moduleEnabled ? 'disabled' : '' }}
                                            class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-violet-500 focus:ring-violet-500 focus:ring-offset-0 bg-white dark:bg-gray-900"
                                        />
                                        <div class="min-w-0">
                                            <span class="text-sm text-gray-800 dark:text-gray-200 group-hover:text-gray-900 dark:group-hover:text-white">{{ $feature['label'] }}</span>
                                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $feature['description'] }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif

                    </div>
                @endforeach

                <p class="text-xs text-gray-400 pt-1">Disabled modules and features disappear from navigation and their routes become inaccessible until re-enabled. Changes apply on the next page load after saving.</p>
            </div>
        </div>
        @endif

        {{-- ── Show Import Settings ──────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">

            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-blue-100 dark:bg-blue-900 p-2">
                        <x-heroicon-o-video-camera class="h-5 w-5 text-blue-600 dark:text-blue-300" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Show Import</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Control how shows are ingested and streamers assigned</p>
                    </div>
                </div>
            </div>

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

        {{-- ── Notifications ────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">

            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-amber-100 dark:bg-amber-900 p-2">
                        <x-heroicon-o-bell class="h-5 w-5 text-amber-600 dark:text-amber-300" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Notifications</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Control who receives each type of in-app notification</p>
                    </div>
                </div>
            </div>

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

        {{-- ── Whatnot Import (owner only) ────────────────────────────────── --}}
        @if ($this->canSeeModuleToggles)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">

            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-indigo-100 dark:bg-indigo-900/40 p-2">
                        <x-heroicon-o-arrow-down-tray class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Whatnot Show Import</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Scrapes completed shows from the Whatnot seller dashboard and creates draft Show records.
                            Requires <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">WHATNOT_EMAIL</code>
                            and <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">WHATNOT_PASSWORD</code> in <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">.env</code>.
                        </p>
                    </div>
                </div>
            </div>

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
                    {{-- Test Connection --}}
                    <button
                        wire:click="testWhatnotConnection"
                        wire:loading.attr="disabled"
                        wire:target="testWhatnotConnection"
                        @if (! $this->whatnotConfigured) disabled @endif
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span wire:loading.remove wire:target="testWhatnotConnection">
                            <x-heroicon-o-signal class="h-4 w-4" />
                        </span>
                        <span wire:loading wire:target="testWhatnotConnection">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </span>
                        <span wire:loading.remove wire:target="testWhatnotConnection">Test Connection</span>
                        <span wire:loading wire:target="testWhatnotConnection">Testing…</span>
                    </button>

                    {{-- Run Import --}}
                    <button
                        wire:click="importWhatnotShows"
                        wire:loading.attr="disabled"
                        wire:target="importWhatnotShows"
                        @if (! $this->whatnotConfigured) disabled @endif
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-indigo-300 dark:border-indigo-700 bg-indigo-50 dark:bg-indigo-950 px-3 py-2 text-sm font-medium text-indigo-700 dark:text-indigo-300 shadow-sm hover:bg-indigo-100 dark:hover:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <span wire:loading.remove wire:target="importWhatnotShows">
                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                        </span>
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
                <div class="px-6 py-3 {{ $whatnotImportStatus === 'success' ? 'bg-emerald-50 dark:bg-emerald-950' : 'bg-red-50 dark:bg-red-950' }} rounded-b-xl">
                    <p class="text-xs font-mono {{ $whatnotImportStatus === 'success' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }} whitespace-pre-wrap">{{ $whatnotImportResult }}</p>
                </div>
            @endif

        </div>
        @endif

        {{-- ── Database Backup (owner only) ──────────────────────────────────── --}}
        @if ($this->canSeeModuleToggles)
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">

            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-teal-100 dark:bg-teal-900/40 p-2">
                        <x-heroicon-o-circle-stack class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Database Backup</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Dumps the database to <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">storage/app/backups/</code>.
                            Also runs automatically every night at 02:00 via the scheduler.
                        </p>
                    </div>
                </div>
            </div>

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
                    <span wire:loading.remove wire:target="runBackup">
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                    </span>
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
                <div class="px-6 py-3 {{ $backupStatus === 'success' ? 'bg-teal-50 dark:bg-teal-950' : 'bg-red-50 dark:bg-red-950' }} rounded-b-xl">
                    <p class="text-xs font-mono {{ $backupStatus === 'success' ? 'text-teal-700 dark:text-teal-300' : 'text-red-700 dark:text-red-300' }} whitespace-pre-wrap">{{ $backupResult }}</p>
                </div>
            @endif

        </div>
        @endif

        {{-- ── System & Maintenance ────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">

            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-gray-100 dark:bg-gray-800 p-2">
                        <x-heroicon-o-wrench-screwdriver class="h-5 w-5 text-gray-600 dark:text-gray-300" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">System & Maintenance</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Run migrations, optimize caches, and keep the app running at peak speed. Run <code class="font-mono bg-gray-100 dark:bg-gray-800 px-1 rounded">php artisan queue:work</code> in the background for async notifications.</p>
                    </div>
                </div>
            </div>

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
                    <span wire:loading.remove wire:target="runMigrations">
                        <x-heroicon-o-circle-stack class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    </span>
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
                    <span wire:loading.remove wire:target="optimizeApp">
                        <x-heroicon-o-bolt class="h-4 w-4" />
                    </span>
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
                    <span wire:loading.remove wire:target="clearCaches">
                        <x-heroicon-o-trash class="h-4 w-4" />
                    </span>
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

            {{-- Last command output --}}
            @if ($lastCommandOutput)
                <div class="px-6 py-4 bg-gray-950 rounded-b-xl">
                    <p class="text-xs font-medium text-gray-400 mb-2 flex items-center gap-1.5">
                        <x-heroicon-o-command-line class="h-3.5 w-3.5" /> Last command output
                    </p>
                    <pre class="text-xs text-emerald-400 font-mono whitespace-pre-wrap leading-relaxed">{{ $lastCommandOutput }}</pre>
                </div>
            @endif

        </div>

        {{-- AI / Ollama --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                <x-heroicon-o-sparkles class="h-5 w-5 text-primary-500" />
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">AI / Ollama Connection</h2>
            </div>

            <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Ollama Base URL</label>
                    <input
                        wire:model.blur="ollama_base_url"
                        type="url"
                        placeholder="http://localhost:11434"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">URL of your Ollama server (default: http://localhost:11434)</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Model Name</label>
                    <input
                        wire:model.blur="ollama_model"
                        type="text"
                        placeholder="llama3.2:3b"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Model to use for chat (e.g. llama3.2, mistral, qwen2.5)</p>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
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
                    <span wire:loading.remove wire:target="testOllamaConnection">
                        <x-heroicon-o-signal class="h-4 w-4" />
                    </span>
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
                <div class="px-6 py-3 rounded-b-xl {{ $ollamaTestStatus === 'success' ? 'bg-emerald-50 dark:bg-emerald-950' : 'bg-red-50 dark:bg-red-950' }}">
                    <p class="text-xs {{ $ollamaTestStatus === 'success' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">
                        {{ $ollamaTestResult }}
                    </p>
                </div>
            @endif
        </div>

        {{-- ── Shipping Surcharge ──────────────────────────────────────────── --}}
        <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
                <x-heroicon-o-truck class="h-5 w-5 text-amber-500" />
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Shipping Surcharge</h2>
            </div>

            <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Rate per Package ($)</label>
                    <input
                        wire:model.blur="shipping_surcharge_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="4.00"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Charge per package over the threshold (default $4.00)</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Threshold Amount ($)</label>
                    <input
                        wire:model.blur="shipping_surcharge_threshold"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="500.00"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 outline-none transition-colors">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Package value threshold that triggers the surcharge (default $500.00)</p>
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
