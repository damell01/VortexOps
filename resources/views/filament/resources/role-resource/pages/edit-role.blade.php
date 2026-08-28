<x-filament-panels::page>
    <div x-data="{ q: '', dirty: false }" x-on:input.window="dirty = true" x-on:change.window="dirty = true" class="space-y-5">
        <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/20 dark:bg-success-500/10"><div class="text-xs font-semibold uppercase text-success-700 dark:text-success-300">Manage</div><div class="mt-1 text-2xl font-bold">{{ $this->accessSummary()['manage'] }}</div><div class="text-xs text-gray-500">Visible + editable</div></div>
            <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-500/20 dark:bg-warning-500/10"><div class="text-xs font-semibold uppercase text-warning-700 dark:text-warning-300">View only</div><div class="mt-1 text-2xl font-bold">{{ $this->accessSummary()['view'] }}</div><div class="text-xs text-gray-500">Visible, editing restricted</div></div>
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5"><div class="text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">Hidden</div><div class="mt-1 text-2xl font-bold">{{ $this->accessSummary()['hidden'] }}</div><div class="text-xs text-gray-500">No page access</div></div>
            <a href="{{ \App\Filament\Pages\NavigationManager::getUrl() }}" class="rounded-xl border border-primary-200 bg-primary-50 p-4 transition hover:border-primary-400 dark:border-primary-500/20 dark:bg-primary-500/10"><div class="text-xs font-semibold uppercase text-primary-700 dark:text-primary-300">Preview</div><div class="mt-1 font-bold text-gray-950 dark:text-white">Preview this role →</div><div class="mt-1 text-xs text-gray-500">Open Navigation Manager</div></a>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div><div class="font-semibold text-gray-950 dark:text-white">Page access</div><div class="text-sm text-gray-500">Manage = visible + editable · View only = visible without editing · Hidden = no access.</div></div>
                <div class="w-full lg:w-80"><input x-model="q" x-on:input.debounce.150ms="document.querySelectorAll('.fi-section').forEach(s => { const title=(s.innerText||'').toLowerCase(); s.style.display = !q || title.includes(q.toLowerCase()) ? '' : 'none'; })" type="search" placeholder="Search modules or pages…" class="w-full rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-gray-950" /></div>
            </div>
        </div>

        <form wire:submit="save">
            {{ $this->form }}
            <div class="sticky bottom-4 z-20 mt-6 flex items-center justify-between rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
                <div class="text-sm text-gray-500"><span x-show="dirty" x-cloak>Unsaved permission changes</span><span x-show="!dirty">Changes are saved when you click Save.</span></div>
                <x-filament::button type="submit" x-on:click="dirty = false">Save Role Access</x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
