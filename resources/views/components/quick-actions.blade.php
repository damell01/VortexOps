<div class="fixed bottom-6 right-6 z-40 space-y-2" x-data="{ open: false }">
    {{-- Floating action button --}}
    <button
        type="button"
        x-on:click="open = !open"
        class="relative w-14 h-14 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 text-white shadow-lg hover:shadow-xl hover:scale-110 transition-all duration-200 flex items-center justify-center"
        x-show="!open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-0"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-0"
        title="Quick actions (Cmd+E)"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m0 0h6" />
        </svg>
    </button>

    {{-- Menu backdrop --}}
    <div
        x-show="open"
        x-on:click="open = false"
        class="fixed inset-0 z-30"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        style="display: none"
    ></div>

    {{-- Menu items --}}
    <div
        x-show="open"
        class="absolute bottom-16 right-0 space-y-2 bg-white dark:bg-gray-900 rounded-xl shadow-xl p-2 border border-gray-200 dark:border-gray-700"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-2"
        style="display: none; min-width: 200px"
    >
        <a href="{{ route('filament.admin.pages.dashboard') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" /></svg>
            <span>Dashboard</span>
        </a>

        <a href="{{ route('filament.admin.pages.inventory-scanner') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 11-2 0V5H5v14h6a1 1 0 110 2H4a1 1 0 01-1-1V4z" /></svg>
            <span>Scan Inventory</span>
        </a>

        <a href="{{ route('filament.admin.resources.shows.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 11-2 0V5H5v14h6a1 1 0 110 2H4a1 1 0 01-1-1V4z" /></svg>
            <span>Shows</span>
        </a>

        <a href="{{ route('filament.admin.resources.streamers.index') }}" class="flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3z" /></svg>
            <span>Streamers</span>
        </a>

        <hr class="my-1 border-gray-200 dark:border-gray-700" />

        <button
            type="button"
            x-on:click="open = false"
            class="w-full flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors text-left"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 15.707a1 1 0 010-1.414l5-5a1 1 0 011.414 0l5 5a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
            <span>Close</span>
        </button>
    </div>
</div>

<script>
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        document.dispatchEvent(new CustomEvent('toggle-quick-actions'));
    }
});
</script>
