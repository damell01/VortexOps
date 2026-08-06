<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <span class="text-2xl">📦</span>
                    Inventory & Fulfillment
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $totalItems }} active items</p>
            </div>
            <a href="{{ route('filament.admin.resources.inventory-items.index') }}"
                class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded font-medium transition">
                Manage →
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                <p class="text-xs text-blue-600 dark:text-blue-400 font-semibold">Ready to Ship</p>
                <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $readyToShip }}</p>
            </div>

            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3">
                <p class="text-xs text-purple-600 dark:text-purple-400 font-semibold">In Transit</p>
                <p class="text-2xl font-bold text-purple-900 dark:text-purple-100">{{ $inTransit }}</p>
            </div>

            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3">
                <p class="text-xs text-green-600 dark:text-green-400 font-semibold">Delivered</p>
                <p class="text-2xl font-bold text-green-900 dark:text-green-100">{{ $delivered }}</p>
            </div>

            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-3">
                <p class="text-xs text-amber-600 dark:text-amber-400 font-semibold">Low Stock</p>
                <p class="text-2xl font-bold text-amber-900 dark:text-amber-100">{{ $lowStockCount }}</p>
            </div>
        </div>

        <div class="space-y-2">
            <a href="{{ route('filament.admin.pages.fulfillment-dashboard') }}"
                class="block w-full text-center px-3 py-2 bg-blue-100 dark:bg-blue-900/30 hover:bg-blue-200 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded text-sm font-medium transition">
                View Fulfillment Dashboard
            </a>
            <a href="{{ route('filament.admin.resources.inventory-items.index') }}"
                class="block w-full text-center px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded text-sm font-medium transition">
                Manage Inventory
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
