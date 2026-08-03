{{-- Empty state with guidance and action suggestions --}}
@props(['icon', 'title', 'description', 'actions' => []])

<div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 px-8 py-12 text-center space-y-6">
    {{-- Icon --}}
    @if($icon)
        <div class="flex justify-center">
            <div class="text-gray-300 dark:text-gray-600">
                {!! $icon !!}
            </div>
        </div>
    @endif

    {{-- Text --}}
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ $title }}</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>
    </div>

    {{-- Actions --}}
    @if(count($actions) > 0)
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            @foreach($actions as $action)
                <a href="{{ $action['url'] }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg {{ $loop->first ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700' }} text-sm font-medium transition-colors">
                    @if(isset($action['icon']))
                        {!! $action['icon'] !!}
                    @endif
                    {{ $action['label'] }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Additional help --}}
    {{ $slot }}
</div>
