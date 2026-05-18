<x-filament-panels::page>
    <div class="space-y-6 max-w-2xl">

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
                    wire:model.live="brand_name"
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
                <input wire:model.live="ollama_base_url" id="ollama_base_url" type="text" placeholder="http://localhost:11434"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono" />
            </div>

            <div class="px-6 py-4 grid grid-cols-2 gap-4">
                <div>
                    <label for="ollama_model" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">Model</label>
                    <input wire:model.live="ollama_model" id="ollama_model" type="text" placeholder="llama3.2"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono" />
                </div>
                <div>
                    <label for="ollama_timeout" class="block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">Timeout (seconds)</label>
                    <input wire:model.live="ollama_timeout" id="ollama_timeout" type="number" min="5" max="300"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none" />
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-950 rounded-b-xl">
                <p class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-2">Quick start</p>
                <div class="space-y-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                    <p><span class="text-gray-400">$</span> ollama serve</p>
                    <p><span class="text-gray-400">$</span> ollama pull {{ $ollama_model ?: 'llama3.2' }}</p>
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
