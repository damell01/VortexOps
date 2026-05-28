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
                        @elseif (file_exists(public_path(\App\Support\Branding::DEFAULT_LOGO_ASSET)))
                            <img src="{{ asset(\App\Support\Branding::DEFAULT_LOGO_ASSET) }}" alt="Default logo" class="max-h-10 max-w-full object-contain" />
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
                    placeholder="{{ \App\Support\Branding::DEFAULT_NAME }}"
                    class="w-full max-w-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none"
                />
                <p class="mt-1 text-xs text-gray-400">Shown in the sidebar header when no logo is set.</p>
            </div>

            {{-- Primary color --}}
            <div class="px-6 py-4">
                <label class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">Primary Color</label>
                <div class="flex items-center gap-3 flex-wrap">

                    {{-- Preset swatches --}}
                    @foreach ($this->brandColorPresets as $preset)
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
                            placeholder="{{ \App\Support\Branding::DEFAULT_PRIMARY_COLOR }}"
                            class="w-24 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-2.5 py-1.5 text-xs font-mono text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                        />
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-400">Applies to buttons, badges, active nav items, and accent elements. The default Vortex palette uses aqua accents against deep indigo surfaces. Reload after saving to see the change.</p>
            </div>

        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-slate-100 dark:bg-slate-800 p-2">
                        <x-heroicon-o-squares-2x2 class="h-5 w-5 text-slate-600 dark:text-slate-300" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Workspace Modules</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Choose which major admin sections stay visible inside the app.</p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 space-y-3">
                @foreach ($this->availableModules as $slug => $module)
                    <label class="flex items-start gap-3 rounded-xl border border-gray-200 dark:border-gray-700 px-4 py-3 cursor-pointer hover:border-violet-300 dark:hover:border-violet-700 transition-colors">
                        <input
                            type="checkbox"
                            wire:model.live="enabled_modules"
                            value="{{ $slug }}"
                            class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-violet-600 focus:ring-violet-500 focus:ring-offset-0 bg-white dark:bg-gray-900"
                        />
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $module['label'] }}</span>
                                <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $module['group'] }}</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $module['description'] }}</p>
                        </div>
                    </label>
                @endforeach

                <p class="text-xs text-gray-400">Hidden modules disappear from navigation and their admin pages stop being accessible until you re-enable them. Review &amp; Feedback also controls the client feedback portal and review mode overlay.</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-cyan-100 dark:bg-cyan-900 p-2">
                        <x-heroicon-o-adjustments-horizontal class="h-5 w-5 text-cyan-600 dark:text-cyan-300" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Topbar Controls</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Choose which helper buttons appear beside search, notifications, and profile controls.</p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Show Review Button</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Lets staff launch page review mode from the admin topbar.</p>
                </div>
                <button
                    wire:click="$toggle('show_review_button')"
                    type="button"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2
                        {{ $show_review_button ? 'bg-violet-600' : 'bg-gray-200 dark:bg-gray-700' }}"
                    role="switch"
                    aria-checked="{{ $show_review_button ? 'true' : 'false' }}"
                >
                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                        {{ $show_review_button ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </div>

            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Show Guided Tour Button</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Keeps the page-level tour launcher visible in the topbar.</p>
                </div>
                <button
                    wire:click="$toggle('show_tour_button')"
                    type="button"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2
                        {{ $show_tour_button ? 'bg-violet-600' : 'bg-gray-200 dark:bg-gray-700' }}"
                    role="switch"
                    aria-checked="{{ $show_tour_button ? 'true' : 'false' }}"
                >
                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                        {{ $show_tour_button ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </div>
        </div>

        {{-- ── AI Settings ───────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">

            <div class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-violet-100 dark:bg-violet-900 p-2">
                        <x-heroicon-o-sparkles class="h-5 w-5 text-violet-600 dark:text-violet-300" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">AI Assistant</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Local AI via Ollama — no data leaves your server</p>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Enable AI Assistant</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Shows the floating AI button on every admin page</p>
                </div>
                <button
                    wire:click="$toggle('ai_enabled')"
                    type="button"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2
                        {{ $ai_enabled ? 'bg-violet-600' : 'bg-gray-200 dark:bg-gray-700' }}"
                    role="switch"
                    aria-checked="{{ $ai_enabled ? 'true' : 'false' }}"
                >
                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                        {{ $ai_enabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </div>

            <div class="px-6 py-4">
                <label for="ollama_base_url" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">Ollama Base URL</label>
                <input wire:model.blur="ollama_base_url" id="ollama_base_url" type="text" placeholder="http://localhost:11434"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono" />
            </div>

            <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="ollama_model" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">Model</label>
                    <input wire:model.blur="ollama_model" id="ollama_model" type="text" placeholder="llama3.2:3b"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono" />
                </div>
                <div>
                    <label for="ollama_timeout" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">Timeout (seconds)</label>
                    <input wire:model.blur="ollama_timeout" id="ollama_timeout" type="number" min="5" max="600"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none" />
                    <p class="mt-1 text-xs text-gray-400">For larger local models, 120 to 180 seconds is a safer default.</p>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-950 rounded-b-xl">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Quick start</p>
                <div class="space-y-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                    <p><span class="text-gray-400">$</span> ollama serve</p>
                    <p><span class="text-gray-400">$</span> ollama pull {{ $ollama_model ?: 'llama3.2:3b' }}</p>
                </div>
            </div>
        </div>

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

            <div class="px-6 py-4 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Auto-assign High-confidence Streamers</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">When AI is confident about a streamer match, assign them automatically</p>
                </div>
                <button
                    wire:click="$toggle('auto_assign_confident_streamers')"
                    type="button"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2
                        {{ $auto_assign_confident_streamers ? 'bg-violet-600' : 'bg-gray-200 dark:bg-gray-700' }}"
                    role="switch"
                    aria-checked="{{ $auto_assign_confident_streamers ? 'true' : 'false' }}"
                >
                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                        {{ $auto_assign_confident_streamers ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
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
