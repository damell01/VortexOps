<x-filament-panels::page>
    <style>
        /* Ensure form buttons are visible and accessible on mobile */
        [x-filament::page] .fi-form {
            position: relative;
        }

        @media (max-width: 768px) {
            [x-filament::page] .fi-form {
                display: flex;
                flex-direction: column;
            }

            [x-filament::page] .fi-form > * {
                width: 100%;
            }

            /* Ensure form actions stay visible and clickable on mobile */
            [x-filament::page] .fi-form-actions {
                position: relative !important;
                bottom: auto !important;
                display: flex !important;
                flex-direction: column-reverse;
                gap: 0.75rem;
                margin-top: 1.5rem;
                padding: 1rem;
                background-color: inherit;
                border-top: 1px solid rgb(229, 231, 235);
            }

            [x-filament::page] .fi-form-actions button {
                width: 100% !important;
                min-height: 44px !important;
                font-size: 16px !important;
            }
        }
    </style>

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
