@props([
    'title' => 'Review Your Submission',
    'description' => 'Make sure everything looks correct before submitting.',
    'items' => [],
    'actions' => [],
])

{{-- Submit Summary Modal Component --}}
<div class="space-y-6 animate-fade-in">
    {{-- Header --}}
    <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">{{ $title }}</h2>
        <p class="text-gray-600 dark:text-gray-400">{{ $description }}</p>
    </div>

    {{-- Summary Items Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($items as $item)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center gap-2">
                        @if(isset($item['icon']))
                            <span class="text-2xl">{{ $item['icon'] }}</span>
                        @endif
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $item['label'] }}</h3>
                    </div>
                    @if(isset($item['status']))
                        <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full
                                   {{ $item['status'] === 'success'
                                       ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200'
                                       : 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200' }}">
                            {{ $item['status'] === 'success' ? '✓' : '⚠️' }}
                        </span>
                    @endif
                </div>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $item['value'] }}</p>
                @if(isset($item['description']))
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $item['description'] }}</p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Warnings or Notes --}}
    @if(isset($warnings))
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
            <div class="flex gap-3">
                <span class="text-2xl">⚠️</span>
                <div>
                    <h4 class="font-semibold text-amber-900 dark:text-amber-200 mb-1">Please Review</h4>
                    <ul class="list-disc list-inside space-y-1 text-sm text-amber-800 dark:text-amber-300">
                        @foreach($warnings as $warning)
                            <li>{{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Action Buttons --}}
    @if(count($actions) > 0)
        <div class="flex gap-3 justify-end flex-wrap">
            {{ $slot }}
        </div>
    @endif
</div>

<script>
    // Ensure accessibility
    document.addEventListener('DOMContentLoaded', () => {
        const summary = document.currentScript?.closest('.space-y-6');
        if (summary) {
            summary.focus();
        }
    });
</script>
