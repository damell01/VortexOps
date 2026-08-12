<x-filament-panels::page>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col" style="height: 100dvh;">
        <!-- Header -->
        <div class="sticky top-0 z-40 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-md safe-area-inset-top">
            <div class="px-4 py-4 flex justify-between items-center">
                <h1 class="text-lg font-bold text-gray-900 dark:text-white">VortexOps Scanner</h1>
                <div class="text-right">
                    <p class="text-xs text-gray-500">{{ auth()->user()->name }}</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white" wire:poll-50ms="updateStats">
                        {{ $totalScanned ?? 0 }} scanned
                    </p>
                </div>
            </div>

            <!-- Mode Selector Tabs -->
            <div class="grid grid-cols-4 gap-1 px-4 pb-4">
                @foreach(['inventory' => '📦', 'receiving' => '📥', 'shipping' => '📤', 'lookup' => '🔍'] as $m => $icon)
                    <button
                        wire:click="selectMode('{{ $m }}')"
                        class="py-2 px-2 rounded-lg text-center text-xs font-semibold transition active:opacity-75 touch-manipulation
                            {{ $mode === $m
                                ? 'bg-blue-500 text-white'
                                : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-300'
                            }}"
                        style="min-height: 44px;"
                    >
                        <div class="text-xl">{{ $icon }}</div>
                        <div class="text-xs mt-0.5">{{ ucfirst($m) }}</div>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-y-auto pb-24">
            @if(!$sessionActive)
                <!-- Mode Selection Cards -->
                <div class="p-4 space-y-3">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
                            <strong>{{ match($mode) {
                                'inventory' => 'Search & locate items. Track locations. Audit stock.',
                                'receiving' => 'Verify incoming shipments. Compare to packing slip.',
                                'shipping' => 'Verify order completeness before packing.',
                                'lookup' => 'Quick item search. View cost & inventory.',
                                default => 'Scan items',
                            } }}</strong>
                        </p>
                        <button
                            wire:click="startScanning"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition active:opacity-75 touch-manipulation"
                            style="min-height: 48px;"
                        >
                            Start Scanning
                        </button>
                    </div>

                    <!-- Mode-specific setup -->
                    @if($mode === 'receiving')
                        <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Select Pallet</label>
                            <select
                                wire:model="selectedPalletId"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                style="min-height: 44px;"
                            >
                                <option value="">Choose pallet...</option>
                                @foreach($pallets ?? [] as $pallet)
                                    <option value="{{ $pallet->id }}">{{ $pallet->reference }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($mode === 'inventory')
                        <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                            <label class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">Location</label>
                            <select
                                wire:model="selectedLocationId"
                                class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                style="min-height: 44px;"
                            >
                                <option value="">All locations</option>
                                @foreach($locations ?? [] as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            @else
                <!-- Active Scanning Session -->
                <div class="p-4 space-y-4">
                    <!-- Camera Component -->
                    <livewire:barcode-scanner lazy-loading />

                    <!-- Scanning Stats -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-green-50 dark:bg-green-900/30 rounded-lg p-3 text-center border border-green-200 dark:border-green-700">
                            <p class="text-xs text-green-600 dark:text-green-400">Items Scanned</p>
                            <p class="text-2xl font-bold text-green-900 dark:text-green-100">{{ $totalScanned ?? 0 }}</p>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900/30 rounded-lg p-3 text-center border border-yellow-200 dark:border-yellow-700">
                            <p class="text-xs text-yellow-600 dark:text-yellow-400">Duplicates</p>
                            <p class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">{{ count($duplicates ?? []) }}</p>
                        </div>
                    </div>

                    <!-- Scanned Items List -->
                    @if(!empty($scannedItems))
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-700">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Items ({{ count($scannedItems) }})</p>
                            </div>
                            <ul class="divide-y divide-gray-200 dark:divide-gray-700 max-h-64 overflow-y-auto">
                                @foreach($scannedItems as $item)
                                    <li class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <div class="flex justify-between gap-3">
                                            <div class="flex-1 min-w-0">
                                                <p class="font-semibold text-gray-900 dark:text-white truncate">{{ $item['name'] ?? 'Unknown' }}</p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $item['sku'] ?? $item['barcode'] }}</p>
                                            </div>
                                            <span class="text-green-600 font-bold text-lg">✓</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Duplicates Warning -->
                    @if(!empty($duplicates))
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border-2 border-yellow-300 dark:border-yellow-700 rounded-lg p-4">
                            <p class="text-sm font-semibold text-yellow-900 dark:text-yellow-100 mb-2">⚠️ {{ count($duplicates) }} Duplicate(s)</p>
                            <ul class="space-y-1 text-xs text-yellow-800 dark:text-yellow-200">
                                @foreach($duplicates as $dup)
                                    <li>{{ $dup['name'] ?? 'Item' }} - {{ $dup['timestamp'] ?? '' }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <!-- Fixed Bottom Actions -->
        <div class="fixed bottom-0 left-0 right-0 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 p-4 space-y-2 safe-area-inset-bottom">
            @if($sessionActive)
                <div class="grid grid-cols-2 gap-3">
                    <button
                        wire:click="endSession"
                        class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-lg transition active:opacity-75 touch-manipulation"
                        style="min-height: 48px;"
                    >
                        End Session
                    </button>
                    <button
                        wire:click="saveSession"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition active:opacity-75 touch-manipulation"
                        style="min-height: 48px;"
                    >
                        Save & Review
                    </button>
                </div>
            @endif
        </div>
    </div>

    <script>
        // Vibration feedback
        document.addEventListener('scanSuccess', () => {
            if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
        });
        document.addEventListener('scanWarning', () => {
            if (navigator.vibrate) navigator.vibrate([100]);
        });
        document.addEventListener('scanError', () => {
            if (navigator.vibrate) navigator.vibrate([100, 50, 100, 50, 100]);
        });

        // Keep screen on during scanning
        if (document.documentElement.requestFullscreen) {
            document.addEventListener('livewire:navigating', () => {
                document.exitFullscreen().catch(() => {});
            });
        }
    </script>
</x-filament-panels::page>
