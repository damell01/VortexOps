<x-filament-panels::page>
    {{-- Include shared camera scanner component inside page --}}
    @include('filament.components.camera-barcode-scanner')

    @if ($this->containerScanMode)
        {{-- Scan Mode View --}}
        @include('filament.pages.create-inventory-item-scan')
    @else
        {{-- Normal Form Mode --}}
        <div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/30">
            <p class="mb-2 text-sm font-semibold text-blue-900 dark:text-blue-200">
                💡 Quick Container Creation
            </p>
            <p class="mb-4 text-sm text-blue-800 dark:text-blue-300">
                Need to create a case with items inside? Use our fast scan mode to enter container name + scan barcodes.
            </p>
            <button
                wire:click="enableContainerScan"
                class="inline-block rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700"
            >
                Use Scan Mode →
            </button>
        </div>

        {{-- Default Filament Form via Slot --}}
        {{ $this->form }}
    @endif
</x-filament-panels::page>
