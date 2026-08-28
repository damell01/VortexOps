<x-filament-panels::page>
    <div x-data="{ q: '', dirty: false }" x-on:input.window="dirty = true" x-on:change.window="dirty = true" class="space-y-5 vx-role-editor">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-primary-600">Role access</div>
                    <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ \Illuminate\Support\Str::headline($this->record->name) }}</h2>
                    <p class="mt-1 text-sm text-gray-500">Set one simple access level for each page. You only need to open the modules you want to change.</p>
                </div>
                <a href="{{ \App\Filament\Pages\NavigationManager::getUrl() }}" class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm font-semibold text-primary-700 transition hover:border-primary-400 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-300">Preview navigation as this role →</a>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/20 dark:bg-success-500/10"><div class="text-xs font-bold uppercase tracking-wide text-success-700 dark:text-success-300">Manage</div><div class="mt-1 text-2xl font-bold">{{ $this->accessSummary()['manage'] }}</div><div class="text-xs text-gray-500">Can open and edit</div></div>
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/20 dark:bg-warning-500/10"><div class="text-xs font-bold uppercase tracking-wide text-warning-700 dark:text-warning-300">View only</div><div class="mt-1 text-2xl font-bold">{{ $this->accessSummary()['view'] }}</div><div class="text-xs text-gray-500">Can open, editing restricted</div></div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5"><div class="text-xs font-bold uppercase tracking-wide text-gray-600 dark:text-gray-300">Hidden</div><div class="mt-1 text-2xl font-bold">{{ $this->accessSummary()['hidden'] }}</div><div class="text-xs text-gray-500">Cannot access the page</div></div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-success-50 px-3 py-1.5 font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-300">Manage = view + edit</span>
                    <span class="rounded-full bg-warning-50 px-3 py-1.5 font-semibold text-warning-700 dark:bg-warning-500/10 dark:text-warning-300">View only = read access</span>
                    <span class="rounded-full bg-gray-100 px-3 py-1.5 font-semibold text-gray-600 dark:bg-white/5 dark:text-gray-300">Hidden = blocked</span>
                </div>
                <input x-model="q" x-on:input.debounce.150ms="document.querySelectorAll('.vx-role-editor .fi-section').forEach(s => { const title=(s.innerText||'').toLowerCase(); s.style.display = !q || title.includes(q.toLowerCase()) ? '' : 'none'; })" type="search" placeholder="Find a module or page…" class="w-full rounded-lg border-gray-300 bg-white text-sm lg:w-80 dark:border-white/10 dark:bg-gray-950" />
            </div>
        </div>

        <form wire:submit="save">
            {{ $this->form }}
            <div class="sticky bottom-4 z-20 mt-6 flex items-center justify-between rounded-xl border border-gray-200 bg-white/95 p-3 shadow-xl backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
                <div class="text-sm text-gray-500"><span x-show="dirty" x-cloak class="font-semibold text-warning-600">You have unsaved access changes.</span><span x-show="!dirty">Open a module, choose Manage / View only / Hidden, then save.</span></div>
                <x-filament::button type="submit" size="lg" x-on:click="dirty = false">Save Role Access</x-filament::button>
            </div>
        </form>
    </div>

    <style>
        .vx-role-editor .vx-role-access-row { align-items:center; padding:.75rem .25rem; border-bottom:1px solid rgb(229 231 235 / .8); }
        .dark .vx-role-editor .vx-role-access-row { border-bottom-color:rgb(255 255 255 / .08); }
        .vx-role-editor .vx-role-access-row:last-child { border-bottom:0; }
        .vx-role-editor .fi-fo-radio { margin:0; }
        @media (max-width: 767px) { .vx-role-editor .vx-role-access-row { padding:1rem .25rem; } }
    </style>
</x-filament-panels::page>
