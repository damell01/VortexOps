<x-filament-panels::page>
    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}

        <x-filament::button type="submit" size="lg" class="mt-6">
            Create Identity & Save
        </x-filament::button>
    </x-filament-panels::form>

    @include('filament.components.camera-barcode-scanner')

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.on('barcode-scanned', (event) => {
                @this.scannedBarcode = event.detail.value;
            });
        });
    </script>
</x-filament-panels::page>
