<div class="barcode-scanner space-y-4">
    <!-- Camera Section -->
    @if($showCamera)
        <div class="bg-gray-900 rounded-lg overflow-hidden border-2 border-gray-700">
            <!-- Camera Feed -->
            <div
                id="zxing-scanner"
                class="w-full"
                style="aspect-ratio: 16/9; min-height: 240px; background: #000;"
            ></div>

            <!-- Status and Controls -->
            <div class="bg-gray-800 px-4 py-3 space-y-3">
                <!-- Status Message -->
                <div class="text-center">
                    <p
                        id="scanner-status"
                        class="text-sm font-medium text-gray-300"
                    >
                        {{ $cameraStatus }}
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 justify-center">
                    <!-- Flashlight Button -->
                    <button
                        wire:click="toggleFlashlight"
                        class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-medium text-white transition active:opacity-75 touch-manipulation {{ $flashlightOn ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-gray-600 hover:bg-gray-700' }}"
                        style="min-height: 44px;"
                    >
                        <span class="text-xl">{{ $flashlightOn ? '💡' : '🔦' }}</span>
                        <span class="text-sm">{{ $flashlightOn ? 'Flashlight On' : 'Flashlight' }}</span>
                    </button>

                    <!-- Close Button -->
                    <button
                        wire:click="toggleCamera"
                        class="flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-medium bg-red-600 hover:bg-red-700 text-white transition active:opacity-75 touch-manipulation"
                        style="min-height: 44px;"
                    >
                        <span class="text-xl">✕</span>
                        <span class="text-sm">Close</span>
                    </button>
                </div>
            </div>
        </div>
    @else
        <!-- Camera Closed State -->
        <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-6 text-center border-2 border-dashed border-gray-300 dark:border-gray-600">
            <div class="text-4xl mb-3">📷</div>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">Camera Scanner</p>
            <button
                wire:click="toggleCamera"
                class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold bg-blue-500 hover:bg-blue-600 text-white transition active:opacity-75 touch-manipulation"
                style="min-height: 48px; min-width: 140px;"
            >
                <span class="text-lg">📷</span>
                <span>Start Camera</span>
            </button>
        </div>
    @endif

    <!-- Manual Input Fallback -->
    <div class="space-y-2">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Or enter barcode manually:
        </label>
        <form wire:submit="submitManual" class="flex gap-2">
            <input
                type="text"
                wire:model="manualInput"
                placeholder="Type or paste barcode..."
                class="flex-1 px-4 py-3 rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-300 focus:outline-none transition touch-manipulation"
                style="min-height: 48px; font-size: 16px;"
                autocomplete="off"
            />
            <button
                type="submit"
                class="px-6 py-3 rounded-lg font-semibold bg-green-500 hover:bg-green-600 text-white transition active:opacity-75 touch-manipulation"
                style="min-height: 48px; min-width: 48px; display: flex; align-items: center; justify-content: center;"
            >
                ✓
            </button>
        </form>
        @error('manualInput')
            <p class="text-sm text-red-500">{{ $message }}</p>
        @enderror
    </div>
</div>

@vite('resources/js/barcode-scanner.js')

<script>
    // Listen for camera toggle events from Livewire
    document.addEventListener('livewire:updated', () => {
        setTimeout(() => {
            const scanner = document.getElementById('zxing-scanner');
            if (scanner && scanner.children.length === 0) {
                // Camera was turned on - initialize scanner
                import('/resources/js/barcode-scanner.js').then(m => m.initScanner?.());
            }
        }, 100);
    });

    // Listen for flashlight toggle
    document.addEventListener('livewire:updated', () => {
        import('/resources/js/barcode-scanner.js').then(m => {
            if (window.flashlightState !== undefined) {
                m.toggleFlashlight?.(window.flashlightState);
            }
        });
    });

    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        import('/resources/js/barcode-scanner.js').then(m => m.stopScanner?.());
    });
</script>
