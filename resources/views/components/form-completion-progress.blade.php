@props([
    'totalFields' => 0,
    'filledFields' => 0,
])

@php
    $percentage = $totalFields > 0 ? round(($filledFields / $totalFields) * 100) : 0;
@endphp

<div class="space-y-2">
    <div class="flex items-center justify-between text-sm">
        <span class="font-medium text-gray-700 dark:text-gray-300">Form Completion</span>
        <span class="text-gray-600 dark:text-gray-400">{{ $filledFields }}/{{ $totalFields }}</span>
    </div>
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
        <div
            class="bg-gradient-to-r from-blue-500 to-cyan-500 h-full rounded-full transition-all duration-300 ease-out"
            style="width: {{ $percentage }}%"
        ></div>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400">
        @if($percentage === 100)
            ✓ All fields completed!
        @elseif($percentage >= 75)
            Almost there! {{ 100 - $percentage }}% remaining
        @elseif($percentage >= 50)
            Half way there! {{ 100 - $percentage }}% remaining
        @else
            {{ $percentage }}% complete
        @endif
    </p>
</div>

<style>
    @keyframes pulse-glow {
        0%, 100% {
            box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.3);
        }
        50% {
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0);
        }
    }

    .form-completion-progress:has(.bg-gradient-to-r) {
        animation: pulse-glow 2s infinite;
    }
</style>
