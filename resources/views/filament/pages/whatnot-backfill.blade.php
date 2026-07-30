<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                🚀 Whatnot Data Backfill
            </x-slot>

            <x-slot name="description">
                Run Whatnot imports and syncs directly from the admin panel. Supports shows, orders, shipments, and ledger data.
            </x-slot>

            <form wire:submit="runImport" class="space-y-6">
                {{ $this->form }}

                <div class="flex gap-3 pt-6">
                    <x-filament::button
                        type="submit"
                        :disabled="$this->isRunning"
                        color="success"
                        icon="heroicon-m-play"
                    >
                        Start Import
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        wire:click="clear"
                        color="gray"
                        icon="heroicon-m-trash-2"
                    >
                        Clear Log
                    </x-filament::button>
                </div>

                @if ($this->isRunning)
                    <div class="rounded-lg bg-blue-50 dark:bg-blue-950 p-4 border border-blue-200 dark:border-blue-800">
                        <p class="text-sm text-blue-700 dark:text-blue-300">
                            ⏳ Import is running...
                        </p>
                    </div>
                @endif
            </form>
        </x-filament::section>

        <!-- Output log with live updating -->
        <x-filament::section>
            <x-slot name="heading">
                📋 Output Log
            </x-slot>

            <div class="bg-gray-950 dark:bg-gray-950 rounded-lg p-4 border border-gray-700 font-mono text-sm text-gray-100 max-h-96 overflow-y-auto whitespace-pre-wrap break-words">
                {{ $this->output }}
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
