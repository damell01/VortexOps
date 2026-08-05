<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Form Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Select Items & Options</h2>

            <form wire:submit="generateLabels" class="space-y-4">
                {{ $this->form }}

                <div class="flex gap-2 justify-end pt-4">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg font-medium transition">
                        <x-heroicon-o-printer class="h-4 w-4" />
                        Generate Labels
                    </button>
                </div>
            </form>
        </div>

        <!-- Preview Section -->
        @if($this->generateLabels())
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Label Preview</h2>
                <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg font-medium transition">
                    <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                    Print Labels
                </button>
            </div>

            <div class="print-area" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; background: white;">
                <!-- Labels will be rendered here -->
                {!! $this->generateLabels() !!}
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <strong>💡 Printing Tips:</strong>
                <br/>• Use Avery 4x6" thermal labels or compatible stock
                <br/>• Set printer margins to 0.1" on all sides
                <br/>• Print in portrait orientation
                <br/>• Click "Print Labels" or press Ctrl+P to open print dialog
            </p>
        </div>
        @else
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 p-12 text-center">
            <x-heroicon-o-qr-code class="h-12 w-12 text-gray-400 mx-auto mb-3" />
            <p class="text-gray-600 dark:text-gray-400">Select items above to generate printable barcode labels</p>
        </div>
        @endif
    </div>

    <style>
        @media print {
            body { margin: 0; padding: 0; }
            .fi-page { padding: 0; margin: 0; }
            .print-area { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; }
            .barcode-label { page-break-inside: avoid; }
        }
    </style>
</x-filament-panels::page>
