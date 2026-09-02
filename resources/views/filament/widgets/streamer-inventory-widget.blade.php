<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="text-2xl">📦</span>
                    Your Inventory
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $locationCount }} location(s)</p>
            </div>
            <a href="{{ route('filament.admin.resources.inventory-items.index') }}" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded-lg font-medium transition">Browse →</a>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                <p class="text-xs text-blue-600 dark:text-blue-400 font-semibold">Products</p>
                <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $totalItems }}</p>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                <p class="text-xs text-green-600 dark:text-green-400 font-semibold">Units Available</p>
                <p class="text-2xl font-bold text-green-900 dark:text-green-100">{{ $totalQuantity }}</p>
            </div>
        </div>

        @if ($lowStockCount > 0)
            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3 mb-4">
                <div class="flex items-center gap-2">
                    <span class="text-lg">⚠️</span>
                    <div><p class="text-xs font-semibold text-amber-800 dark:text-amber-200">Low Stock</p><p class="text-sm font-bold text-amber-900 dark:text-amber-100">{{ $lowStockCount }} item(s) need attention</p></div>
                </div>
            </div>
        @endif

        <a href="{{ route('filament.admin.resources.inventory-items.index') }}" class="min-h-11 flex items-center justify-center text-center px-3 py-2 bg-blue-100 dark:bg-blue-900/30 hover:bg-blue-200 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded-lg text-sm font-medium transition">Browse All Inventory</a>
    </x-filament::section>
</x-filament-widgets::widget>
