<x-filament-panels::page>
    <div class="space-y-3 sm:space-y-5" data-vx-page="inventory-center">
        <section data-tour="inventory-start" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="p-4 sm:p-5">
                <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Inventory Center</div>
                <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">What are you trying to do?</h2>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Search first when you are checking stock. Use Quick Scan for something in your hand, Quick Add for a simple new item, and Receive Shipment when inventory arrived on a pallet.</p>
            </div>

            <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4">
                @php
                    $user = auth()->user();
                    $canReceive = $user?->isAdmin() || $user?->isOwner();
                @endphp

                <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('quick-add') }}" class="group bg-white p-3 hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800/70 sm:p-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/40 sm:h-9 sm:w-9">
                        <x-heroicon-o-bolt class="h-4 w-4 sm:h-5 sm:w-5" />
                    </div>
                    <div class="mt-2 text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Quick Add</div>
                    <div class="mt-0.5 text-[10px] leading-4 text-gray-500 sm:text-xs">Add a simple item fast.</div>
                </a>

                @if($canReceive)
                    <a href="{{ \App\Filament\Pages\InventoryScanner::getUrl() }}" class="group bg-white p-3 hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800/70 sm:p-4">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/40 sm:h-9 sm:w-9">
                            <x-heroicon-o-qr-code class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <div class="mt-2 text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Quick Scan</div>
                        <div class="mt-0.5 text-[10px] leading-4 text-gray-500 sm:text-xs">Look up or add stock by barcode.</div>
                    </a>

                    <a href="{{ \App\Filament\Resources\PalletResource::getUrl('index') }}" class="group bg-white p-3 hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800/70 sm:p-4">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 sm:h-9 sm:w-9">
                            <x-heroicon-o-inbox-arrow-down class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <div class="mt-2 text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Receive Shipment</div>
                        <div class="mt-0.5 text-[10px] leading-4 text-gray-500 sm:text-xs">Open a pallet and scan it in.</div>
                    </a>
                @endif

                <a href="{{ \App\Filament\Resources\InventoryLocationResource::getUrl('index') }}" class="group bg-white p-3 hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800/70 sm:p-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300 sm:h-9 sm:w-9">
                        <x-heroicon-o-map-pin class="h-4 w-4 sm:h-5 sm:w-5" />
                    </div>
                    <div class="mt-2 text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Locations</div>
                    <div class="mt-0.5 text-[10px] leading-4 text-gray-500 sm:text-xs">See where stock is stored.</div>
                </a>
            </div>
        </section>

        <div data-tour="inventory-health">
            <x-kpi-row :stats="$this->getStats()" />
        </div>

        <section data-tour="inventory-list" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Inventory Items</h2>
                <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">Search by item name, SKU, or barcode. Open an item for stock by location, history, transfer, adjustment, and case/container details.</p>
            </div>
            <div class="p-1 sm:p-2">
                {{ $this->table }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
