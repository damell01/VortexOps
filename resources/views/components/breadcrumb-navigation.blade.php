@props(['items' => []])

{{-- Breadcrumb Navigation Component --}}
<nav class="flex items-center gap-2 text-sm mb-6 animate-fade-in" aria-label="Breadcrumb">
    <ol class="flex items-center gap-2">
        {{-- Home link --}}
        <li>
            <a href="{{ route('filament.admin.pages.dashboard') }}"
               class="flex items-center gap-1 text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                </svg>
                <span class="hidden sm:inline">Home</span>
            </a>
        </li>

        {{-- Breadcrumb items --}}
        @foreach($items as $index => $item)
            <li class="flex items-center gap-2">
                {{-- Separator --}}
                <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                </svg>

                {{-- Link or text --}}
                @if(isset($item['url']) && $item['url'])
                    <a href="{{ $item['url'] }}"
                       class="text-gray-600 dark:text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors flex items-center gap-1"
                       @if($index === count($items) - 1) aria-current="page" @endif>
                        @if(isset($item['icon']))
                            <span class="text-base">{{ $item['icon'] }}</span>
                        @endif
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="text-gray-900 dark:text-white font-medium flex items-center gap-1"
                          @if($index === count($items) - 1) aria-current="page" @endif>
                        @if(isset($item['icon']))
                            <span class="text-base">{{ $item['icon'] }}</span>
                        @endif
                        {{ $item['label'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

<style>
    @media (max-width: 640px) {
        nav {
            font-size: 0.875rem;
        }

        .hidden.sm\:inline {
            display: none;
        }
    }
</style>
