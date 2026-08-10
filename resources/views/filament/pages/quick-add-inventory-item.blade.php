@php
    use Filament\Support\Enums\MaxWidth;
@endphp

<x-filament-panels::page>
    <div class="mx-auto max-w-2xl">
        <!-- Progress Indicator -->
        <div class="mb-8 flex justify-between">
            @for ($i = 1; $i <= 3; $i++)
                <div class="flex flex-col items-center flex-1">
                    <div class="flex items-center w-full">
                        @if ($i > 1)
                            <div class="flex-1 h-0.5 {{ $i <= $this->currentStep ? 'bg-success-500' : 'bg-gray-300' }}"></div>
                        @endif
                        <div class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full {{ $i <= $this->currentStep ? 'bg-success-500 text-white' : 'bg-gray-300 text-gray-600' }}">
                            @if ($i < $this->currentStep)
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            @else
                                {{ $i }}
                            @endif
                        </div>
                        @if ($i < 3)
                            <div class="flex-1 h-0.5 {{ $i < $this->currentStep ? 'bg-success-500' : 'bg-gray-300' }}"></div>
                        @endif
                    </div>
                    <span class="mt-2 text-sm font-medium text-gray-600">
                        @if ($i === 1) Item Details
                        @elseif ($i === 2) Add Stock
                        @else Review
                        @endif
                    </span>
                </div>
            @endfor
        </div>

        <!-- Form -->
        <div class="space-y-6">
            {{ $this->form }}

            <!-- Navigation Buttons -->
            <div class="flex justify-between gap-3 pt-6">
                @if ($this->currentStep > 1)
                    <button
                        type="button"
                        wire:click="dispatch('previous-step')"
                        class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                    >
                        ← Back
                    </button>
                @else
                    <div></div>
                @endif

                @if ($this->currentStep < 3)
                    <button
                        type="button"
                        wire:click="dispatch('next-step')"
                        class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition font-medium"
                    >
                        Next →
                    </button>
                @else
                    <div class="flex gap-3">
                        <button
                            type="button"
                            wire:click="dispatch('previous-step')"
                            class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
                        >
                            ← Back
                        </button>
                        <button
                            type="button"
                            wire:click="dispatch('submit-wizard')"
                            class="px-6 py-2 bg-success-600 text-white rounded-lg hover:bg-success-700 transition font-medium"
                        >
                            ✓ Add to Inventory
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
