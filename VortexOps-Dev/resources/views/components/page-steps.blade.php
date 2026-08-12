{{-- Step-by-step workflow guide for multi-step pages --}}
@props(['steps' => [], 'currentStep' => 1])

<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950 dark:to-indigo-950 p-4 mb-6">
    <div class="flex items-center gap-4 overflow-x-auto pb-2">
        @foreach($steps as $i => $step)
            @php $step_num = $i + 1; @endphp
            <div class="flex items-center gap-2 flex-shrink-0">
                {{-- Step circle --}}
                <div class="relative">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold
                        {{ $step_num <= $currentStep
                            ? 'bg-blue-600 text-white'
                            : 'bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-300' }}">
                        @if($step_num < $currentStep)
                            ✓
                        @else
                            {{ $step_num }}
                        @endif
                    </div>
                </div>

                {{-- Step label --}}
                <div class="flex-shrink-0">
                    <p class="text-xs font-semibold {{ $step_num <= $currentStep ? 'text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400' }}">
                        {{ $step['label'] }}
                    </p>
                    @if(isset($step['description']))
                        <p class="text-xs text-gray-600 dark:text-gray-400">{{ $step['description'] }}</p>
                    @endif
                </div>

                {{-- Connector --}}
                @if($i < count($steps) - 1)
                    <div class="w-4 h-0.5 {{ $step_num < $currentStep ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600' }} flex-shrink-0"></div>
                @endif
            </div>
        @endforeach
    </div>
</div>
