<x-filament-panels::page>
    {{-- The shared camera scanner (the one Scan Inventory uses) announces a
         read with a window event; hand it straight to the page. --}}
    <div class="w-full max-w-2xl mx-auto space-y-4 sm:space-y-6" data-vx-touch-page
        x-data
        x-on:barcode-scanned.window="$event.detail?.value && $wire.handleBarcodeScan($event.detail.value)">
        <x-touch-mode-toggle />
        {{-- Product Scanner Section --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Scan Product</h2>

            <button type="button"
                x-on:click="window.dispatchEvent(new CustomEvent('open-camera-scanner', { detail: { title: 'Scan product', helper: 'Hold the barcode inside the frame. The product loads automatically.' } }))"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary-600 px-6 py-3 font-semibold text-white transition hover:bg-primary-500 active:opacity-75 touch-manipulation"
                style="min-height: 48px;">
                <x-heroicon-o-camera class="h-5 w-5" />
                Scan with camera
            </button>

            {{-- Manual Barcode Input --}}
            <div class="mt-4 space-y-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Manual Entry</label>
                <div class="flex gap-2">
                    <input
                        wire:model="barcode"
                        wire:keydown.enter="scanBarcode"
                        type="text"
                        autocomplete="off"
                        placeholder="Enter barcode or SKU"
                        class="min-w-0 flex-1 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500"
                        style="min-height: 44px;"
                    />
                    <button
                        wire:click="scanBarcode"
                        wire:loading.attr="disabled"
                        wire:target="scanBarcode"
                        class="px-5 py-2 bg-primary-600 hover:bg-primary-500 text-white font-semibold rounded-lg transition active:opacity-75 touch-manipulation focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
                        style="min-height: 44px;"
                    >
                        <span wire:loading.remove wire:target="scanBarcode">Search</span>
                        <span wire:loading wire:target="scanBarcode">Searching…</span>
                    </button>
                </div>
            </div>

            {{-- Keyword Search: for when there's no barcode to scan --}}
            <div class="relative mt-4 space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">No barcode? Find it by name</label>
                <input
                    wire:model.live.debounce.300ms="productSearch"
                    type="text"
                    autocomplete="off"
                    placeholder="Search by product name, SKU or barcode…"
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                    style="min-height: 44px;"
                />

                @if($productOptions !== null)
                    <div class="absolute z-20 max-h-56 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        @forelse($productOptions as $option)
                            <button type="button" wire:click="selectSearchResult({{ $option['id'] }})"
                                class="block w-full px-4 py-2.5 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-800">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $option['name'] }}</span>
                                <span class="ml-2 text-xs text-gray-500">{{ $option['sku'] ?: '—' }}</span>
                            </button>
                        @empty
                            <div class="px-4 py-2.5 text-xs text-gray-500">No matching products.</div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        {{-- Product Details Section --}}
        @if($product)
        <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-200 dark:border-green-700 rounded-lg p-6">
            <div class="space-y-4">
                <div>
                    <p class="text-xs text-green-700 dark:text-green-400 uppercase font-semibold">Selected Product</p>
                    <h3 class="text-xl font-bold text-green-900 dark:text-green-100">{{ $product->name }}</h3>
                    <p class="text-sm text-green-600 dark:text-green-400 mt-1">
                        SKU: <span class="font-mono">{{ $product->sku }}</span>
                        @if($product->barcode)
                            | Barcode: <span class="font-mono">{{ $product->barcode }}</span>
                        @endif
                    </p>
                </div>

                {{-- Quantity Selector --}}
                <div class="border-t border-green-200 dark:border-green-700 pt-4">
                    <label class="block text-sm font-medium text-green-900 dark:text-green-100 mb-3">Quantity</label>
                    <div x-data="{ keypad: false, digits: String($wire.quantity || 1), typeDigit(d) { this.digits = this.digits === '0' ? d : this.digits + d; $wire.set('quantity', Number(this.digits || 0)); }, erase() { this.digits = this.digits.slice(0, -1); $wire.set('quantity', Number(this.digits || 0)); } }" class="space-y-2">
                        <div class="flex items-center gap-2">
                            <input wire:model.live.number="quantity" type="number" min="1" step="1"
                                inputmode="numeric" autocomplete="off" aria-label="Quantity to add"
                                x-on:input="digits = $event.target.value"
                                class="min-h-12 w-32 rounded-lg border-2 border-green-600 bg-white px-3 text-center text-lg font-bold text-green-900 dark:border-green-500 dark:bg-gray-800 dark:text-white" />
                            <button type="button" x-on:click="digits = String($wire.quantity || 1); keypad = !keypad"
                                x-bind:aria-expanded="keypad.toString()" aria-label="Toggle on-screen number pad"
                                class="min-h-12 rounded-lg border border-green-600 bg-white px-4 font-semibold text-green-800 dark:bg-gray-800 dark:text-green-200">
                                Number pad
                            </button>
                        </div>
                        <div x-show="keypad" x-cloak class="grid max-w-xs grid-cols-3 gap-2" aria-label="On-screen quantity keypad">
                            <template x-for="digit in ['1','2','3','4','5','6','7','8','9']" :key="digit">
                                <button type="button" x-on:click="typeDigit(digit)" x-text="digit"
                                    class="min-h-12 rounded-lg border border-green-300 bg-white text-lg font-bold text-green-900 dark:bg-gray-800 dark:text-white"></button>
                            </template>
                            <button type="button" x-on:click="digits = ''; $wire.set('quantity', 0)"
                                class="min-h-12 rounded-lg border border-green-300 bg-white font-semibold text-green-900 dark:bg-gray-800 dark:text-white">Clear</button>
                            <button type="button" x-on:click="typeDigit('0')"
                                class="min-h-12 rounded-lg border border-green-300 bg-white text-lg font-bold text-green-900 dark:bg-gray-800 dark:text-white">0</button>
                            <button type="button" x-on:click="erase()"
                                class="min-h-12 rounded-lg border border-green-300 bg-white font-semibold text-green-900 dark:bg-gray-800 dark:text-white">⌫</button>
                        </div>
                        <p class="text-xs text-green-800 dark:text-green-200">If your Bluetooth scanner hides the phone keyboard, use Number pad.</p>
                    </div>
                </div>

                {{-- Location Selector --}}
                <div class="border-t border-green-200 dark:border-green-700 pt-4">
                    <label class="block text-sm font-medium text-green-900 dark:text-green-100 mb-2">Storage Location</label>
                    <select
                        wire:model="locationId"
                        class="w-full px-4 py-2 rounded-lg border border-green-300 dark:border-green-600 bg-white dark:bg-gray-700 text-green-900 dark:text-green-100"
                        style="min-height: 44px;"
                    >
                        <option value="">Select location...</option>
                        @foreach($this->locations as $location)
                            <option value="{{ $location->id }}">
                                {{ $location->name }}
                                @if($location->type)
                                    ({{ ucfirst($location->type) }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Add Button --}}
                <button
                    wire:click="addStock"
                    wire:loading.attr="disabled"
                    wire:target="addStock"
                    class="w-full mt-4 px-6 py-4 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition active:opacity-75 touch-manipulation text-lg"
                    style="min-height: 52px;"
                >
                    <span wire:loading.remove wire:target="addStock">Add {{ (int) $quantity }} Unit{{ (int) $quantity === 1 ? '' : 's' }} to Stock</span>
                    <span wire:loading wire:target="addStock">Adding stock…</span>
                </button>
            </div>
        </div>
        @else
        <div class="bg-gray-50 dark:bg-gray-800/50 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8 text-center">
            <p class="text-gray-500 dark:text-gray-400 text-lg">
                👇 Scan a barcode, enter an SKU, or search by name to get started
            </p>
        </div>
        @endif

        {{-- Recent Additions --}}
        @if(!empty($recentAdds))
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-3">Recent Additions</h3>
            <ul class="space-y-2">
                @foreach($recentAdds as $add)
                <li class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg text-sm">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $add['product_name'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $add['quantity'] }} units → {{ $add['location'] }}
                        </p>
                    </div>
                    <span class="text-xs text-gray-400 dark:text-gray-500">{{ $add['timestamp'] }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>

    <script>
        document.addEventListener('scanSuccess', () => {
            if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
        });
        document.addEventListener('scanError', () => {
            if (navigator.vibrate) navigator.vibrate([100, 50, 100, 50, 100]);
        });
    </script>
</x-filament-panels::page>
