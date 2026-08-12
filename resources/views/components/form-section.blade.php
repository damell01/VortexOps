{{-- Grouped form section with title, description, and help --}}
@props(['title', 'description' => null, 'help' => null, 'icon' => null])

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                @if($icon)
                    <div class="flex-shrink-0 text-blue-600 dark:text-blue-400">
                        {!! $icon !!}
                    </div>
                @endif
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                    @if($description)
                        <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ $description }}</p>
                    @endif
                </div>
            </div>

            @if($help)
                <div class="relative group flex-shrink-0">
                    <button type="button" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-gray-900 text-white text-xs rounded-lg p-2 shadow-lg opacity-0 group-hover:opacity-100 pointer-events-none group-hover:pointer-events-auto transition-opacity z-10">
                        {{ $help }}
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Content --}}
    <div class="px-6 py-4 space-y-4">
        {{ $slot }}
    </div>
</div>
